<?php

namespace App\Http\Controllers;

use App\Models\ClassBooking;
use App\Models\ClassSession;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * The home page for students and teachers: subscription, next class, courses and students.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('home', [
            'user' => $user,
            'subscriptions' => Subscription::with('plan')->where('student_id', $user->id)->usable()->orderBy('ends_at')->get(),
            'owing' => -min(0, (int) $user->enrolments()->where('legacy_balance_ugx', '<', 0)->sum('legacy_balance_ugx')),
            'nextClass' => ClassBooking::with('classSession.course', 'classSession.teacher')
                ->where('student_id', $user->id)->where('status', 'booked')
                ->whereHas('classSession', fn ($q) => $q->where('ends_at', '>', now()))
                ->get()->sortBy('classSession.starts_at')->first(),
            'nextSessions' => ClassSession::with('course')->where('teacher_id', $user->id)->where('status', 'scheduled')
                ->where('ends_at', '>', now())->orderBy('starts_at')->limit(5)->get(),
            'enrolments' => $user->isTeacher()
                ? $user->teachingEnrolments()->with('student', 'course')->where('status', 'active')->get()
                : $user->enrolments()->with('course', 'teacher', 'level')->get(),
        ]);
    }
}
