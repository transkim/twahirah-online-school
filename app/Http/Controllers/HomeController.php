<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * A simple home page for students and teachers until the apps are ready.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('home', [
            'user' => $user,
            'subscriptions' => Subscription::with('plan')->where('student_id', $user->id)->usable()->orderBy('ends_at')->get(),
            'enrolments' => $user->isTeacher()
                ? $user->teachingEnrolments()->with('student', 'course')->where('status', 'active')->get()
                : $user->enrolments()->with('course', 'teacher', 'level')->get(),
        ]);
    }
}
