<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\GradeBand;
use App\Models\Mark;
use App\Models\User;
use App\Services\LearningService;
use App\Services\ReportCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Marks, grade bands, report cards and certificates. */
class ResultsController extends Controller
{
    public function reportCard(Request $request, ReportCardService $cards, ?User $student = null): View
    {
        $user = $request->user();
        $student ??= $user;
        abort_unless($student->id === $user->id || in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE], true)
            || ($user->isTeacher() && $user->teachingEnrolments()->where('student_id', $student->id)->exists()), 403);

        $tz = config('school.timezone');
        $from = $request->date('from', 'Y-m-d', $tz) ?? now($tz)->startOfYear();
        $to = $request->date('to', 'Y-m-d', $tz) ?? now($tz);

        return view('results.report-card', [
            'student' => $student,
            'rows' => $cards->for($student, $from, $to),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'weights' => config('school.mark_weights'),
            'certificates' => Certificate::with('course', 'level')->where('student_id', $student->id)->get(),
        ]);
    }

    /** Teachers and the office enter assessment marks for a course. */
    public function marks(Request $request, LearningService $learning): View
    {
        $user = $request->user();
        $courses = Course::orderBy('name')->orderBy('mode')->get()
            ->filter(fn (Course $c) => $learning->isStaff($user) || $learning->canAddTo($user, $c->id))->values();
        $course = $courses->firstWhere('id', $request->integer('course')) ?? $courses->first();

        $enrolments = $course ? Enrolment::with('student', 'level')->where('course_id', $course->id)->where('status', 'active')
            ->when($user->isTeacher(), fn ($q) => $q->where('teacher_id', $user->id))
            ->get()->sortBy('student.name') : collect();

        return view('results.marks', [
            'courses' => $courses,
            'course' => $course,
            'enrolments' => $enrolments,
            'recent' => $course ? Mark::with('student')->where('course_id', $course->id)->latest()->limit(60)->get() : collect(),
        ]);
    }

    public function storeMarks(Request $request, LearningService $learning): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:191'],
            'given_on' => ['required', 'date'],
            'scores' => ['required', 'array'],
            'scores.*' => ['nullable', 'integer', 'between:0,100'],
        ]);
        $user = $request->user();
        abort_unless($user->isAdmin() || $learning->canAddTo($user, (int) $data['course_id']), 403);

        $allowed = Enrolment::where('course_id', $data['course_id'])->where('status', 'active')
            ->when($user->isTeacher(), fn ($q) => $q->where('teacher_id', $user->id))->pluck('student_id')->all();

        $saved = 0;
        foreach ($data['scores'] as $studentId => $score) {
            if ($score === null || ! in_array((int) $studentId, $allowed, true)) {
                continue;
            }
            Mark::create(['student_id' => (int) $studentId, 'course_id' => $data['course_id'], 'kind' => 'assessment', 'title' => $data['title'],
                'score' => (int) $score, 'given_on' => $data['given_on'], 'recorded_by' => $user->id]);
            $saved++;
        }

        return back()->with('status', "Saved {$saved} marks.");
    }

    public function grading(): View
    {
        return view('results.grading', ['bands' => GradeBand::orderByDesc('min_score')->get(), 'weights' => config('school.mark_weights')]);
    }

    public function updateGrading(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.name' => ['nullable', 'string', 'max:20'],
            'bands.*.min_score' => ['nullable', 'integer', 'between:0,100'],
            'bands.*.comment' => ['nullable', 'string', 'max:191'],
        ]);

        $bands = collect($data['bands'])->filter(fn ($b) => filled($b['name'] ?? null) && isset($b['min_score']));
        if (! $bands->contains(fn ($b) => (int) $b['min_score'] === 0)) {
            return back()->withErrors(['bands' => 'One grade must start at 0, so every score gets a grade.']);
        }

        GradeBand::query()->delete();
        $bands->each(fn ($b) => GradeBand::create(['name' => $b['name'], 'min_score' => (int) $b['min_score'], 'comment' => $b['comment'] ?? null]));

        return back()->with('status', 'Grades saved.');
    }

    public function certificate(Request $request, Certificate $certificate): View
    {
        $user = $request->user();
        abort_unless($certificate->student_id === $user->id || ! $user->isStudent(), 403);

        return view('results.certificate', ['certificate' => $certificate->load('student', 'course', 'level', 'exam')]);
    }

    /** Anyone can check that a certificate is real by its code. */
    public function verify(string $code): View
    {
        return view('results.verify', ['certificate' => Certificate::with('student', 'course', 'level')->where('code', strtoupper($code))->first(), 'code' => $code]);
    }
}
