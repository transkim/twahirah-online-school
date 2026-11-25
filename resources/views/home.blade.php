@extends('layouts.app')
@section('content')
<h1>Assalamu alaikum, {{ $user->name }}</h1>
@if ($user->isStudent())
    <div class="card">
        <h2>Your subscription</h2>
        @forelse ($subscriptions as $s)
            <p><b>{{ $s->plan->name }}</b> · until {{ $s->ends_at->timezone($user->timezone)->format('j M Y, H:i') }}
                @if ($s->sessions_total !== null) · {{ $s->sessionsLeft() }} of {{ $s->sessions_total }} Zoom sessions left @endif</p>
        @empty
            <p class="warn">You have no active subscription. Please subscribe to open your lessons and classes.</p>
        @endforelse
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
