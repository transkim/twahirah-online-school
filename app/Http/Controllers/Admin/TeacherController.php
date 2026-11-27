<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\TeacherAvailability;
use App\Models\TeacherCourse;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(): View
    {
        return view('admin.teachers.index', [
            'teachers' => User::where('role', User::ROLE_TEACHER)
                ->withCount(['teachingEnrolments as students' => fn ($q) => $q->where('status', 'active')])
                ->with('teacherCourses.course')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:users,username'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $teacher = User::create($data + ['role' => User::ROLE_TEACHER]);

        return redirect()->route('admin.teachers.show', $teacher)->with('status', 'Teacher added.');
    }

    public function show(User $teacher): View
    {
        abort_unless($teacher->isTeacher(), 404);

        return view('admin.teachers.show', [
            'teacher' => $teacher->load('teacherCourses.course', 'availabilities'),
            'students' => $teacher->teachingEnrolments()->with('student', 'course', 'level')->where('status', 'active')->get(),
            'courses' => Course::with('levels')->where('active', true)->orderBy('name')->orderBy('mode')->get(),
        ]);
    }

    public function addCourse(Request $request, User $teacher): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'course_level_id' => ['nullable', 'exists:course_levels,id'],
        ]);

        TeacherCourse::firstOrCreate(['teacher_id' => $teacher->id] + $data + ['course_level_id' => null]);

        return back()->with('status', 'Course added to this teacher.');
    }

    public function removeCourse(User $teacher, TeacherCourse $teacherCourse): RedirectResponse
    {
        abort_unless($teacherCourse->teacher_id === $teacher->id, 404);
        $teacherCourse->delete();

        return back()->with('status', 'Course removed from this teacher.');
    }

    public function addAvailability(Request $request, User $teacher): RedirectResponse
    {
        $data = $request->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $teacher->availabilities()->create($data);

        return back()->with('status', 'Free hours added.');
    }

    public function removeAvailability(User $teacher, TeacherAvailability $availability): RedirectResponse
    {
        abort_unless($availability->teacher_id === $teacher->id, 404);
        $availability->delete();

        return back()->with('status', 'Free hours removed.');
    }
}
