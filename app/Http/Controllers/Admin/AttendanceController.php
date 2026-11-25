<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrolment;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /** The register for one day: every active student, present or not yet marked. */
    public function index(Request $request): View
    {
        $date = Carbon::parse($request->query('date', now(config('school.timezone'))->toDateString()))->toDateString();

        $enrolments = Enrolment::with('student', 'course', 'teacher')
            ->where('status', 'active')
            ->when($request->user()->isTeacher(), fn ($q) => $q->where('teacher_id', $request->user()->id))
            ->get()
            ->sortBy(fn ($e) => [$e->teacher?->name, $e->course->name, $e->student->name]);

        $marks = Attendance::where('date', $date)->whereIn('enrolment_id', $enrolments->pluck('id'))->get()->keyBy('enrolment_id');

        return view('admin.attendance', ['date' => $date, 'enrolments' => $enrolments, 'marks' => $marks]);
    }

    public function update(Request $request, Enrolment $enrolment, AttendanceService $attendance): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isTeacher() && $enrolment->teacher_id !== $user->id, 403);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', 'in:present,absent'],
        ]);

        $attendance->mark($enrolment, $data['date'], $data['status'], $user);

        return back()->with('status', 'Register updated.');
    }
}
