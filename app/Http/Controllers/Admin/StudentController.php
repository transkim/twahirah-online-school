<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Enrolment;
use App\Models\Plan;
use App\Models\User;
use App\Services\EnrolmentService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $students = User::where('role', User::ROLE_STUDENT)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('student_number', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")))
            ->with(['enrolments.course', 'subscriptions' => fn ($q) => $q->usable()])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.students.index', ['students' => $students, 'search' => $search]);
    }

    public function create(): View
    {
        return view('admin.students.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:191', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:60', 'alpha_dash', 'unique:users,username'],
            'student_number' => ['nullable', 'string', 'max:30', 'unique:users,student_number'],
            'country' => ['nullable', 'string', 'max:60'],
            'timezone' => ['nullable', 'timezone:all'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $student = User::create($data + ['role' => User::ROLE_STUDENT, 'timezone' => $data['timezone'] ?? config('school.timezone')]);

        return redirect()->route('admin.students.show', $student)->with('status', 'Student added.');
    }

    public function show(User $student): View
    {
        abort_unless($student->isStudent(), 404);

        return view('admin.students.show', [
            'student' => $student->load([
                'enrolments.course', 'enrolments.level', 'enrolments.teacher',
                'subscriptions' => fn ($q) => $q->with('plan')->latest('ends_at'),
                'payments' => fn ($q) => $q->with('plan')->latest(),
            ]),
            'attendance' => $student->attendances()->with('enrolment.course')->latest('date')->limit(30)->get(),
            'courses' => Course::with('levels')->where('active', true)->orderBy('name')->get(),
            'plans' => Plan::where('active', true)->orderBy('mode')->orderBy('days_per_week')->orderBy('duration_days')->get(),
            'teachers' => User::where('role', User::ROLE_TEACHER)->where('active', true)->orderBy('name')->get(),
        ]);
    }

    /** Put the student on a course; a teacher and class group are chosen automatically. */
    public function enrol(Request $request, User $student, EnrolmentService $enrolments): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'course_level_id' => ['nullable', 'exists:course_levels,id'],
            'days_per_week' => ['nullable', Rule::in([2, 3, 5])],
        ]);

        $course = Course::findOrFail($data['course_id']);
        $level = isset($data['course_level_id']) ? CourseLevel::where('course_id', $course->id)->findOrFail($data['course_level_id']) : null;
        $enrolment = $enrolments->enrol($student, $course, $level, $data['days_per_week'] ?? null);

        $message = $enrolment->teacher
            ? "Added to {$course->name} with {$enrolment->teacher->name}."
            : "Added to {$course->name}. No teacher teaches this course yet, so please assign one.";

        return back()->with('status', $message);
    }

    public function changeTeacher(Request $request, User $student, Enrolment $enrolment, EnrolmentService $enrolments): RedirectResponse
    {
        abort_unless($enrolment->student_id === $student->id, 404);
        $data = $request->validate(['teacher_id' => ['required', 'exists:users,id']]);

        $enrolments->changeTeacher($enrolment, User::findOrFail($data['teacher_id']));

        return back()->with('status', 'Teacher changed.');
    }

    /** Record money paid at the office (cash or a mobile money payment made outside the app). */
    public function recordPayment(Request $request, User $student, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'method' => ['required', Rule::in(['cash', 'mtn', 'airtel', 'card', 'bank'])],
            'provider_reference' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($data, $student, $subscriptions, $request) {
            $payment = $subscriptions->startPayment($student, Plan::findOrFail($data['plan_id']), $data['method']);
            $payment->update(['recorded_by' => $request->user()->id]);
            $subscriptions->confirmPayment($payment, $data['provider_reference'] ?? null);
        });

        return back()->with('status', 'Payment recorded and subscription started.');
    }

    /** Give a free subscription, for example a scholarship. */
    public function grant(Request $request, User $student, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'note' => ['required', 'string', 'max:191'],
        ]);

        $subscriptions->grant($student, Plan::findOrFail($data['plan_id']), $data['note'].' (given by '.$request->user()->name.')');

        return back()->with('status', 'Free subscription given.');
    }
}
