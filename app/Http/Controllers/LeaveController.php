<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\SchoolNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Teachers and staff ask for leave; the office approves or declines it. */
class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $office = in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE], true);

        return view('leave', [
            'office' => $office,
            'requests' => LeaveRequest::with('user')->when(! $office, fn ($q) => $q->where('user_id', $user->id))
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")->latest()->limit(100)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'max:191'],
        ]);
        $request->user()->leaveRequests()->create($data);

        return back()->with('status', 'Leave asked for. The office will reply.');
    }

    public function decide(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,declined']]);
        $leave->update([...$data, 'decided_by' => $request->user()->id, 'decided_at' => now()]);

        $clashes = $data['status'] === 'approved' ? ClassSession::where('teacher_id', $leave->user_id)->where('status', 'scheduled')
            ->whereDate('starts_at', '>=', $leave->from_date->toDateString())->whereDate('starts_at', '<=', $leave->to_date->toDateString())->count() : 0;

        $leave->user->notify(new SchoolNotice('Leave '.$data['status'], $leave->from_date->format('j M').' to '.$leave->to_date->format('j M'), route('leave.index')));

        return back()->with('status', 'Leave '.$data['status'].'.'.($clashes ? " {$clashes} booked classes fall in these days; move them to another teacher." : ''));
    }
}
