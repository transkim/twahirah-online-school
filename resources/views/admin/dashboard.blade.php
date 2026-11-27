@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
    $hour = (int) now(config('school.timezone'))->format('G');
    $icons = config('navigation.icons');
    $mostStudents = max(1, (int) $busiestCourses->max('active_students'));
@endphp
<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:20px">
    <div>
        <h1 style="margin:0">{{ $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening') }}, {{ strtok(auth()->user()->name, ' ') }}</h1>
        <div class="muted">{{ now(config('school.timezone'))->format('l, j F Y') }}</div>
    </div>
    <div class="row">
        <a class="btn" href="{{ route('admin.students.create') }}">Add student</a>
        <a class="btn light" href="{{ route('admin.attendance') }}">Mark register</a>
        <a class="btn light" href="{{ route('admin.students.index') }}">Record a payment</a>
    </div>
</div>
<div class="grid">
    <div class="card stat"><span>Collected today</span><b>UGX {{ number_format($collectedToday) }}</b><small>Paid subscriptions</small><i class="ic"><svg viewBox="0 0 24 24"><path d="{{ $icons['finance'] }}"/></svg></i></div>
    <div class="card stat orange"><span>Present today</span><b>{{ number_format($presentToday) }}</b><small>Marked on sign-in or Zoom</small><i class="ic"><svg viewBox="0 0 24 24"><path d="{{ $icons['calendar'] }}"/></svg></i></div>
    <div class="card stat purple"><span>Subscribed now</span><b>{{ number_format($subscribed) }}</b><small>Students who can learn</small><i class="ic"><svg viewBox="0 0 24 24"><path d="{{ $icons['courses'] }}"/></svg></i></div>
    <div class="card stat slate"><span>Active students</span><b>{{ number_format($students) }}</b><small>All courses</small><i class="ic"><svg viewBox="0 0 24 24"><path d="{{ $icons['students'] }}"/></svg></i></div>
    <div class="card stat red"><span>Teachers</span><b>{{ number_format($teachers) }}</b><small>Teaching now</small><i class="ic"><svg viewBox="0 0 24 24"><path d="{{ $icons['teachers'] }}"/></svg></i></div>
</div>
<div class="split">
    <div>
        <div class="card">
            <h2>Latest payments</h2>
            <table>
                <tr><th>When</th><th>Student</th><th>Plan</th><th>Amount</th><th>Via</th><th>Status</th></tr>
                @forelse ($recentPayments as $p)
                    <tr><td>{{ $p->created_at->timezone(config('school.timezone'))->format('j M, H:i') }}</td><td><a href="{{ route('admin.students.show', $p->student) }}">{{ $p->student->name }}</a></td><td>{{ $p->plan?->name }}</td><td>UGX {{ number_format($p->amount_ugx) }}</td><td>{{ strtoupper($p->method) }}</td><td><span @class(['pill', 'ok' => $p->status === 'paid'])>{{ $p->status }}</span></td></tr>
                @empty
                    <tr><td colspan="6" class="muted">No payments yet.</td></tr>
                @endforelse
            </table>
        </div>
        <div class="card">
            <h2>Subscriptions ending in the next 3 days</h2>
            <table>
                <tr><th>Student</th><th>Plan</th><th>Ends</th></tr>
                @forelse ($endingSoon as $s)
                    <tr><td><a href="{{ route('admin.students.show', $s->student) }}">{{ $s->student->name }}</a></td><td>{{ $s->plan->name }}</td><td>{{ $s->ends_at->timezone(config('school.timezone'))->format('D j M, H:i') }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">None.</td></tr>
                @endforelse
            </table>
        </div>
    </div>
    <div>
        <div class="card">
            <h2>Quick links</h2>
            <div class="quick">
                <a href="{{ route('admin.students.index') }}"><svg viewBox="0 0 24 24"><path d="{{ $icons['students'] }}"/></svg>Students</a>
                <a href="{{ route('admin.teachers.index') }}"><svg viewBox="0 0 24 24"><path d="{{ $icons['teachers'] }}"/></svg>Teachers</a>
                <a href="{{ route('admin.courses.index') }}"><svg viewBox="0 0 24 24"><path d="{{ $icons['courses'] }}"/></svg>Courses</a>
                <a href="{{ route('admin.plans.index') }}"><svg viewBox="0 0 24 24"><path d="{{ $icons['finance'] }}"/></svg>Plans &amp; prices</a>
                <a href="{{ route('admin.attendance') }}"><svg viewBox="0 0 24 24"><path d="{{ $icons['calendar'] }}"/></svg>Attendance</a>
                <a href="{{ route('admin.section', 'chat') }}"><svg viewBox="0 0 24 24"><path d="{{ $icons['communication'] }}"/></svg>Chat &amp; groups</a>
            </div>
        </div>
        <div class="card">
            <h2>Students by course</h2>
            @forelse ($busiestCourses as $course)
                <div style="margin:10px 0">
                    <div class="row" style="justify-content:space-between;font-size:13.5px"><span>{{ $course->name }} <span class="muted">· {{ $course->mode === 'zoom' ? 'Zoom' : 'Chat' }}</span></span><b>{{ number_format($course->active_students) }}</b></div>
                    <div style="height:6px;border-radius:3px;background:var(--soft);margin-top:4px"><div style="height:6px;border-radius:3px;background:#2d8c1e;width:{{ round($course->active_students / $mostStudents * 100) }}%"></div></div>
                </div>
            @empty
                <p class="muted">No courses yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
