<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrolment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Office reports: who owes, cancelled enrolments, who has gone quiet, teachers, and one student's statement. */
class ReportController extends Controller
{
    public function balances(): View
    {
        $rows = Enrolment::query()->where('legacy_balance_ugx', '<', 0)
            ->select('student_id', DB::raw('sum(legacy_balance_ugx) as owed'), DB::raw('count(*) as courses'))
            ->groupBy('student_id')->orderBy('owed')->paginate(100);

        return view('admin.reports.balances', [
            'rows' => $rows,
            'students' => User::whereIn('id', $rows->pluck('student_id'))->get()->keyBy('id'),
            'total' => -(int) Enrolment::where('legacy_balance_ugx', '<', 0)->sum('legacy_balance_ugx'),
        ]);
    }

    public function cancelled(): View
    {
        return view('admin.reports.cancelled', [
            'enrolments' => Enrolment::with('student', 'course', 'teacher')->where('status', 'cancelled')
                ->orderByDesc('ended_on')->paginate(100),
        ]);
    }

    /** Students studying now who have not signed in or joined a class for a week or more. */
    public function insights(): View
    {
        $quiet = Enrolment::with('student', 'course', 'teacher')->where('status', 'active')
            ->addSelect(['last_present' => DB::table('attendances')->selectRaw('max(date)')->whereColumn('enrolment_id', 'enrolments.id')->where('status', 'present')])
            ->get()
            ->filter(fn ($e) => ! $e->last_present || $e->last_present < now(config('school.timezone'))->subDays(7)->toDateString())
            ->sortBy('last_present');

        $lapsed = User::where('role', User::ROLE_STUDENT)->where('active', true)
            ->whereHas('subscriptions')->whereDoesntHave('subscriptions', fn ($q) => $q->usable())->count();

        return view('admin.reports.insights', ['quiet' => $quiet, 'lapsed' => $lapsed,
            'endingSoon' => Subscription::with('student', 'plan')->usable()->where('ends_at', '<=', now()->addDays(7))->orderBy('ends_at')->get()]);
    }

    public function teachers(): View
    {
        $teachers = User::where('role', User::ROLE_TEACHER)->where('active', true)
            ->withCount(['teachingEnrolments as students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')->get()
            ->map(function (User $t) {
                $sessions = DB::table('class_sessions')->where('teacher_id', $t->id)->where('starts_at', '>=', now()->subDays(30));
                $t->classes_held = (clone $sessions)->where('starts_at', '<', now())->where('status', '!=', 'cancelled')->count();
                $t->present_marks = DB::table('attendances')->join('enrolments', 'enrolments.id', '=', 'attendances.enrolment_id')
                    ->where('enrolments.teacher_id', $t->id)->where('attendances.status', 'present')->where('attendances.date', '>=', now()->subDays(30)->toDateString())->count();

                return $t;
            });

        return view('admin.reports.teachers', ['teachers' => $teachers]);
    }

    /** Everything one student has paid and used. */
    public function statement(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $student = $request->integer('student') ? User::find($request->integer('student')) : null;
        $matches = $search !== '' ? User::where('role', User::ROLE_STUDENT)->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
            ->orWhere('student_number', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))->limit(20)->get() : collect();

        return view('admin.reports.statement', [
            'search' => $search,
            'matches' => $matches,
            'student' => $student,
            'payments' => $student ? Payment::with('plan')->where('student_id', $student->id)->orderBy('created_at')->get() : collect(),
            'subscriptions' => $student ? Subscription::with('plan')->where('student_id', $student->id)->orderBy('starts_at')->get() : collect(),
            'enrolments' => $student ? Enrolment::with('course')->where('student_id', $student->id)->get() : collect(),
        ]);
    }
}
