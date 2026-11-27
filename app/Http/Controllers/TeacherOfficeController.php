<?php

namespace App\Http\Controllers;

use App\Models\Appraisal;
use App\Models\ClassSession;
use App\Models\Feedback;
use App\Models\User;
use App\Models\WorkPlan;
use App\Notifications\SchoolNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Weekly work plans, teacher appraisals and student feedback on classes. */
class TeacherOfficeController extends Controller
{
    private function isOffice(User $user): bool
    {
        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE], true);
    }

    public function workPlans(Request $request): View
    {
        $user = $request->user();
        $week = now(config('school.timezone'))->startOfWeek(Carbon::MONDAY)->toDateString();

        return view('teacher-office.work-plans', [
            'office' => $this->isOffice($user),
            'mine' => $user->isTeacher() ? WorkPlan::where('teacher_id', $user->id)->whereDate('week_start', $week)->first() : null,
            'week' => $week,
            'plans' => WorkPlan::with('teacher')->when(! $this->isOffice($user), fn ($q) => $q->where('teacher_id', $user->id))
                ->latest('week_start')->limit(100)->get(),
        ]);
    }

    public function saveWorkPlan(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isTeacher(), 403);
        $data = $request->validate(['week_start' => ['required', 'date'], 'plan' => ['required', 'string', 'max:10000'], 'done' => ['nullable', 'string', 'max:10000']]);

        WorkPlan::updateOrCreate(['teacher_id' => $request->user()->id, 'week_start' => Carbon::parse($data['week_start'])->toDateString()], [
            'plan' => $data['plan'], 'done' => $data['done'] ?? null,
        ]);

        return back()->with('status', 'Work plan saved.');
    }

    public function commentWorkPlan(Request $request, WorkPlan $plan): RedirectResponse
    {
        $plan->update($request->validate(['office_comment' => ['nullable', 'string', 'max:5000']]));
        $plan->teacher->notify(new SchoolNotice('Comment on your work plan', (string) $plan->office_comment, route('work-plans.index')));

        return back()->with('status', 'Comment saved.');
    }

    public function appraisals(): View
    {
        $since = now()->subDays(90);
        $teachers = User::where('role', User::ROLE_TEACHER)->where('active', true)->orderBy('name')->get()->map(function (User $t) use ($since) {
            $t->classes_held = ClassSession::where('teacher_id', $t->id)->where('starts_at', '>=', $since)->where('starts_at', '<', now())->where('status', '!=', 'cancelled')->count();
            $t->rating = Feedback::where('teacher_id', $t->id)->where('created_at', '>=', $since)->avg('rating');
            $t->ratings = Feedback::where('teacher_id', $t->id)->where('created_at', '>=', $since)->count();
            $t->plans = WorkPlan::where('teacher_id', $t->id)->whereDate('week_start', '>=', $since->toDateString())->count();

            return $t;
        });

        return view('teacher-office.appraisals', ['teachers' => $teachers, 'appraisals' => Appraisal::with('teacher')->latest()->limit(100)->get()]);
    }

    public function storeAppraisal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
            'period' => ['required', 'string', 'max:60'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'to_improve' => ['nullable', 'string', 'max:5000'],
        ]);
        Appraisal::create([...$data, 'appraised_by' => $request->user()->id]);

        return back()->with('status', 'Appraisal saved.');
    }

    public function feedback(Request $request): View
    {
        $user = $request->user();

        return view('teacher-office.feedback', [
            'student' => $user->isStudent(),
            'enrolments' => $user->isStudent() ? $user->enrolments()->with('course', 'teacher')->where('status', 'active')->whereNotNull('teacher_id')->get() : collect(),
            'feedback' => Feedback::with('student', 'teacher', 'course')
                ->when($user->isStudent(), fn ($q) => $q->where('student_id', $user->id))
                ->when($user->isTeacher(), fn ($q) => $q->where('teacher_id', $user->id))
                ->latest()->paginate(50),
        ]);
    }

    public function storeFeedback(Request $request): RedirectResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        $data = $request->validate(['enrolment_id' => ['required', 'integer'], 'rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:2000']]);
        $enrolment = $student->enrolments()->findOrFail($data['enrolment_id']);

        Feedback::create(['student_id' => $student->id, 'teacher_id' => $enrolment->teacher_id, 'course_id' => $enrolment->course_id, 'rating' => $data['rating'], 'comment' => $data['comment'] ?? null]);

        return back()->with('status', 'Thank you. Your feedback was sent to the office.');
    }
}
