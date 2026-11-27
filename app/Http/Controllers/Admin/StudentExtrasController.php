<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Enrolment;
use App\Models\LevelChange;
use App\Models\Topic;
use App\Models\User;
use App\Notifications\SchoolNotice;
use App\Services\StudentNumbers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Graduates, app sign-ups waiting to be checked, free student numbers, levels and topics, and student levels. */
class StudentExtrasController extends Controller
{
    public function graduates(): View
    {
        return view('admin.students.graduates', ['certificates' => Certificate::with('student', 'course', 'level')->latest('issued_at')->paginate(100)]);
    }

    public function selfRegistered(): View
    {
        return view('admin.students.self-registered', [
            'students' => User::with('enrolments.course')->where('role', User::ROLE_STUDENT)->where('self_registered', true)->whereNull('approved_at')->where('active', true)->latest()->get(),
        ]);
    }

    public function decideSelfRegistered(Request $request, User $student, StudentNumbers $numbers): RedirectResponse
    {
        abort_unless($student->self_registered && $student->approved_at === null, 404);
        $data = $request->validate(['action' => ['required', 'in:approve,reject']]);

        if ($data['action'] === 'approve') {
            $student->update(['approved_at' => now(), 'student_number' => $student->student_number ?? $numbers->next()]);
            $student->notify(new SchoolNotice('Welcome to '.config('school.name'), 'Your student number is '.$student->student_number.'.', route('home')));

            return back()->with('status', "{$student->name} approved as {$student->student_number}.");
        }

        $student->update(['active' => false]);

        return back()->with('status', "{$student->name} rejected and switched off.");
    }

    public function freeNumbers(StudentNumbers $numbers): View
    {
        return view('admin.students.free-numbers', ['free' => $numbers->free(), 'next' => $numbers->next()]);
    }

    public function levels(): View
    {
        return view('admin.levels', ['courses' => Course::with('levels.topics')->orderBy('name')->orderBy('mode')->get()]);
    }

    public function storeLevel(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'duration_months' => ['nullable', 'integer', 'between:1,120']]);
        $course->levels()->create([...$data, 'position' => (int) $course->levels()->max('position') + 1]);

        return back()->with('status', 'Level added.');
    }

    public function storeTopic(Request $request, CourseLevel $level): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191']]);
        $level->topics()->create([...$data, 'position' => (int) $level->topics()->max('position') + 1]);

        return back()->with('status', 'Topic added.');
    }

    public function destroyTopic(Topic $topic): RedirectResponse
    {
        $topic->delete();

        return back()->with('status', 'Topic removed.');
    }

    public function studentLevels(Request $request): View
    {
        $courses = Course::with('levels')->orderBy('name')->orderBy('mode')->get();
        $course = $courses->firstWhere('id', $request->integer('course')) ?? $courses->first();

        return view('admin.students.levels', [
            'courses' => $courses, 'course' => $course,
            'enrolments' => $course ? Enrolment::with('student', 'level')->where('course_id', $course->id)->where('status', 'active')->get()->sortBy('student.name') : collect(),
        ]);
    }

    /** Move one or more students to another level. Every change is kept. */
    public function promote(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enrolments' => ['required', 'array', 'min:1'],
            'enrolments.*' => ['integer'],
            'course_level_id' => ['required', 'exists:course_levels,id'],
        ]);
        $level = CourseLevel::findOrFail($data['course_level_id']);

        $moved = DB::transaction(function () use ($data, $level, $request) {
            $moved = 0;
            foreach (Enrolment::whereIn('id', $data['enrolments'])->where('course_id', $level->course_id)->get() as $e) {
                if ($e->course_level_id !== $level->id) {
                    LevelChange::create(['enrolment_id' => $e->id, 'from_level_id' => $e->course_level_id, 'to_level_id' => $level->id, 'changed_by' => $request->user()->id]);
                    $e->update(['course_level_id' => $level->id]);
                    $e->student->notify(new SchoolNotice('You moved to '.$level->name, $level->course->name, route('home')));
                    $moved++;
                }
            }

            return $moved;
        });

        return back()->with('status', "{$moved} students moved to {$level->name}.");
    }
}
