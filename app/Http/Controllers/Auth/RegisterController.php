<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Services\EnrolmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * New students sign themselves up in the app. They can subscribe and start straight away;
 * the office checks the sign-up later and gives a student number.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['courses' => Course::where('active', true)->orderBy('name')->orderBy('mode')->get()]);
    }

    public function store(Request $request, EnrolmentService $enrolments): RedirectResponse
    {
        $key = 'register|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['name' => 'Too many sign-ups from here. Please try again later.']);
        }
        RateLimiter::hit($key, 3600);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[+\d\s()-]{9,20}$/'],
            'gender' => ['nullable', 'in:male,female'],
            'country' => ['nullable', 'string', 'max:60'],
            'course_id' => ['required', 'exists:courses,id'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $student = User::create([
            'name' => $data['name'], 'phone' => preg_replace('/[^\d+]/', '', $data['phone']), 'gender' => $data['gender'] ?? null,
            'country' => $data['country'] ?? null, 'password' => $data['password'], 'role' => User::ROLE_STUDENT, 'self_registered' => true,
        ]);
        $enrolments->enrol($student, Course::findOrFail($data['course_id']));

        Auth::login($student);
        $request->session()->regenerate();

        return redirect()->route('my.subscribe')->with('status', 'Welcome! Choose a plan to open your classes. You sign in next time with your phone number and password.');
    }
}
