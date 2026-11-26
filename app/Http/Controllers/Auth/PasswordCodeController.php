<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\PasswordCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordCodeController extends Controller
{
    public function __construct(private PasswordCodeService $codes) {}

    public function create(): View
    {
        return view('auth.password-code');
    }

    /**
     * Send a code by SMS. The reply does not say whether an account was found,
     * so nobody can use this page to find out who studies at the school.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:191']]);

        $key = 'password-code:'.mb_strtolower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'login' => 'Too many codes asked for. Please try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes.',
            ]);
        }
        RateLimiter::hit($key, 600);

        $accounts = $this->codes->accountsFor($data['login']);
        if ($accounts->count() > 1) {
            throw ValidationException::withMessages([
                'login' => 'This phone number is used by more than one student. Please enter your student number instead, for example T/26/7530.',
            ]);
        }

        $user = $accounts->first();
        if ($user?->phone) {
            $this->codes->sendCode($user);
        }

        $request->session()->put('password_code_user', $user?->phone ? $user->id : null);

        return redirect()->route('password.code.verify')->with('status', $user?->phone
            ? 'We sent a 6-digit code by SMS to '.$this->codes->maskedPhone($user->phone).'.'
            : 'If that matches an account with a phone number, we sent it a 6-digit code by SMS.');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->session()->exists('password_code_user')) {
            return redirect()->route('password.code');
        }

        return view('auth.password-verify');
    }

    /** Check the code, save the new password and sign the person in. */
    public function update(Request $request, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::where('active', true)->find($request->session()->get('password_code_user'));
        if (! $user || ! $this->codes->checkCode($user, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is not right or has expired. Check the SMS, or ask for a new code.']);
        }

        $user->update(['password' => $data['password']]);
        $request->session()->forget('password_code_user');
        Auth::login($user);
        $request->session()->regenerate();

        if ($user->isStudent()) {
            $attendance->recordSignIn($user);
        }

        return redirect()->route($user->isAdmin() || $user->role === User::ROLE_FINANCE ? 'admin.dashboard' : 'home')
            ->with('status', 'Your new password is saved. Use it with your phone number to sign in next time.');
    }
}
