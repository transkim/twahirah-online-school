<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\PasswordCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    /**
     * Sign in with a username, phone number, student number or email, plus a password.
     * A student who signs in is marked present for today's chat classes.
     */
    public function store(Request $request, AttendanceService $attendance, PasswordCodeService $codes): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.mb_strtolower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Too many attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        // A few phone numbers are shared by family members, so try each account that matches.
        $user = $codes->accountsFor($data['login'])
            ->first(fn (User $candidate): bool => Hash::check($data['password'], $candidate->password));

        if (! $user || ! Auth::attempt(['id' => $user->id, 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['login' => 'The username or password is not correct.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        if ($user->isStudent()) {
            $attendance->recordSignIn($user);

            return redirect()->route('home');
        }

        return redirect()->intended(route($user->isTeacher() ? 'home' : 'admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
