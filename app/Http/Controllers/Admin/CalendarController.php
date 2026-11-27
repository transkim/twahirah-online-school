<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Event;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Every live class, event and approved leave on one calendar, a week at a time. */
class CalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tz = config('school.timezone');
        $start = ($request->date('week', 'Y-m-d', $tz) ?? now($tz))->copy()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(7);

        $days = collect(range(0, 6))->mapWithKeys(fn ($i) => [$start->copy()->addDays($i)->toDateString() => collect()]);

        ClassSession::with('course', 'teacher')->withCount(['bookings' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->where('status', '!=', 'cancelled')->whereBetween('starts_at', [$start->copy()->utc(), $end->copy()->utc()])->orderBy('starts_at')->get()
            ->each(fn ($s) => $days[$s->starts_at->timezone($tz)->toDateString()]?->push(['time' => $s->starts_at->timezone($tz)->format('H:i'), 'kind' => 'class',
                'text' => $s->course->name.' · '.$s->teacher->name.' · '.$s->bookings_count.' '.($s->kind === 'group' ? 'in group' : 'one to one')]));

        Event::whereBetween('starts_at', [$start->copy()->utc(), $end->copy()->utc()])->orderBy('starts_at')->get()
            ->each(fn ($e) => $days[$e->starts_at->timezone($tz)->toDateString()]?->push(['time' => $e->starts_at->timezone($tz)->format('H:i'), 'kind' => 'event', 'text' => $e->title.($e->place ? ' · '.$e->place : '')]));

        LeaveRequest::with('user')->where('status', 'approved')->whereDate('from_date', '<', $end->toDateString())->whereDate('to_date', '>=', $start->toDateString())->get()
            ->each(function ($l) use ($days) {
                foreach ($days as $date => $items) {
                    if ($date >= $l->from_date->toDateString() && $date <= $l->to_date->toDateString()) {
                        $items->push(['time' => '', 'kind' => 'leave', 'text' => $l->user->name.' on leave']);
                    }
                }
            });

        return view('admin.calendar', ['days' => $days, 'start' => $start]);
    }
}
