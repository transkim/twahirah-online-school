<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now(config('school.timezone'))->toDateString();
        $dayStart = now(config('school.timezone'))->startOfDay()->utc();

        return view('admin.dashboard', [
            'students' => User::where('role', User::ROLE_STUDENT)->where('active', true)->count(),
            'teachers' => User::where('role', User::ROLE_TEACHER)->where('active', true)->count(),
            'subscribed' => Subscription::usable()->distinct('student_id')->count('student_id'),
            'endingSoon' => Subscription::with('student', 'plan')->usable()
                ->where('ends_at', '<', now()->addDays(3))->orderBy('ends_at')->limit(10)->get(),
            'presentToday' => Attendance::where('date', $today)->where('status', 'present')->count(),
            'collectedToday' => Payment::where('status', 'paid')->where('paid_at', '>=', $dayStart)->sum('amount_ugx'),
            'recentPayments' => Payment::with('student', 'plan')->latest()->limit(10)->get(),
        ]);
    }
}
