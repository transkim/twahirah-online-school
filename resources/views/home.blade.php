@extends('layouts.app')
@section('content')
@php $tz = $user->timezone; @endphp
<h1>Assalamu alaikum, {{ $user->name }}</h1>
@if ($user->isStudent())
    @if ($owing > 0)
        <div class="flash errors">Old balance from before the new system: <b>UGX {{ number_format($owing) }}</b>. Please clear it with the office.</div>
    @endif
    <div class="card">
        <h2>Your subscription</h2>
        @forelse ($subscriptions as $s)
            <p><b>{{ $s->plan->name }}</b> · until {{ $s->ends_at->timezone($tz)->format('j M Y, H:i') }}
                @if ($s->sessions_total !== null) · {{ $s->sessionsLeft() }} of {{ $s->sessions_total }} Zoom sessions left @endif</p>
        @empty
            <p class="warn">You have no active subscription. Please subscribe to open your lessons and classes.</p>
        @endforelse
        <p><a class="btn" href="{{ route('my.subscribe') }}">Subscribe or renew</a></p>
    </div>
    @if ($nextClass)
        <div class="card">
            <h2>Next live class</h2>
            <p><b>{{ $nextClass->classSession->course->name }}</b> with {{ $nextClass->classSession->teacher->name }},
                {{ $nextClass->classSession->starts_at->timezone($tz)->format('l j M, H:i') }}</p>
            <p><a class="btn light" href="{{ route('my.classes') }}">Open my classes</a></p>
        </div>
    @endif
@endif
@if ($user->isTeacher() && $nextSessions->isNotEmpty())
    <div class="card">
        <h2>Your next live classes</h2>
        <table>
            @foreach ($nextSessions as $s)
                <tr><td>{{ $s->starts_at->timezone($tz)->format('D j M, H:i') }}</td><td>{{ $s->course->name }}</td><td class="muted">{{ $s->kind === 'group' ? 'Small group' : 'One to one' }}</td></tr>
            @endforeach
        </table>
    </div>
@endif
<div class="card">
    <h2>{{ $user->isTeacher() ? 'Your students' : 'Your courses' }}</h2>
    <table>
        @foreach ($enrolments as $e)
            <tr>
                <td>{{ $user->isTeacher() ? $e->student->name : $e->course->name }}</td>
                <td class="muted">{{ $user->isTeacher() ? $e->course->name : ($e->level?->name) }}</td>
                <td class="muted">{{ $user->isTeacher() ? '' : 'Teacher: '.($e->teacher?->name ?? 'not yet assigned') }}</td>
            </tr>
        @endforeach
    </table>
</div>
@endsection
