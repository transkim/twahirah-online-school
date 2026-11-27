@extends('layouts.app')
@section('title', 'My live classes')
@section('content')
@php $tz = $student->timezone; $kinds = ['one_to_one' => 'One to one', 'group' => 'Small group']; @endphp
<div class="card">
    <h2>Booked classes</h2>
    <table>
        <tr><th>When ({{ $tz }})</th><th>Course</th><th>Teacher</th><th>Type</th><th></th></tr>
        @forelse ($booked as $b)
            @php $s = $b->classSession; $canJoin = now()->gte($s->starts_at->copy()->subMinutes(10)) && now()->lt($s->ends_at); @endphp
            <tr>
                <td>{{ $s->starts_at->timezone($tz)->format('D j M, H:i') }}</td>
                <td>{{ $s->course->name }}</td>
                <td>{{ $s->teacher->name }}</td>
                <td class="muted">{{ $kinds[$s->kind] ?? $s->kind }}</td>
                <td style="white-space:nowrap">
                    @if ($canJoin)
                        <form class="inline" method="post" action="{{ route('my.classes.join', $b) }}">@csrf<button>Join now</button></form>
                    @elseif ($b->status === 'booked')
                        <form class="inline" method="post" action="{{ route('my.classes.destroy', $b) }}">@csrf @method('delete')<button class="plain">Cancel</button></form>
                    @else
                        <span class="pill ok">{{ $b->status }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No classes booked yet. Choose a day below.</td></tr>
        @endforelse
    </table>
</div>

<div class="card">
    <h2>Book a class</h2>
    @if ($enrolments->isEmpty())
        <p class="muted">You are not on a Zoom course. Chat classes happen in your class group.</p>
    @else
        @if ($subscription)
            <p>Your Zoom plan has <b>{{ $subscription->sessionsLeft() ?? 'unlimited' }}</b> sessions left, until {{ $subscription->ends_at->timezone($tz)->format('j M Y') }}.</p>
        @else
            <p class="warn">You need a Zoom subscription with sessions left to book. <a href="{{ route('my.subscribe') }}">Subscribe</a></p>
        @endif
        <form class="row" method="get" action="{{ route('my.classes') }}">
            <div><label>Course</label><select name="enrolment">@foreach ($enrolments as $e)<option value="{{ $e->id }}" @selected($e->id === $enrolment->id)>{{ $e->course->name }}{{ $e->level ? ' · '.$e->level->name : '' }}</option>@endforeach</select></div>
            <div><label>Day</label><input type="date" name="date" value="{{ $date }}" min="{{ now(config('school.timezone'))->toDateString() }}"></div>
            <button>Show times</button>
        </form>
        <table style="margin-top:14px">
            <tr><th>Time ({{ $tz }})</th><th>Teacher</th><th>Type</th><th>Seats</th><th></th></tr>
            @forelse ($options as $o)
                <tr>
                    <td>{{ $o['starts_at']->copy()->timezone($tz)->format('H:i') }}</td>
                    <td>{{ $teachers[$o['teacher_id']] ?? '' }}</td>
                    <td>{{ $kinds[$o['kind']] }}{{ $o['class_session_id'] ? ' (already started by others)' : '' }}</td>
                    <td class="muted">{{ $o['seats_left'] }} left</td>
                    <td><form class="inline" method="post" action="{{ route('my.classes.store') }}">@csrf
                        <input type="hidden" name="enrolment_id" value="{{ $enrolment->id }}">
                        <input type="hidden" name="starts_at" value="{{ $o['starts_at']->toIso8601String() }}">
                        <input type="hidden" name="teacher_id" value="{{ $o['teacher_id'] }}">
                        <input type="hidden" name="kind" value="{{ $o['kind'] }}">
                        <input type="hidden" name="class_session_id" value="{{ $o['class_session_id'] }}">
                        <button @disabled(! $subscription)>Book</button></form></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No free times on this day. Try another day.</td></tr>
            @endforelse
        </table>
        <p class="muted" style="margin-top:10px">You can cancel up to {{ config('school.change_booking_hours') }} hours before a class. Joining a class uses one Zoom session and marks you present.</p>
    @endif
</div>
@endsection
