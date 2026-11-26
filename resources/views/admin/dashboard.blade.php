@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php $hour = (int) now(config('school.timezone'))->format('G'); @endphp
<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:18px">
    <h1 style="margin:0">{{ $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening') }}, {{ strtok(auth()->user()->name, ' ') }}
        <span class="muted" style="font-size:14px;font-weight:400;margin-left:8px">{{ now(config('school.timezone'))->format('l, j F Y') }}</span></h1>
    <div class="row">
        <a class="btn" href="{{ route('admin.students.create') }}">Add student</a>
        <a class="btn light" href="{{ route('admin.attendance') }}">Mark register</a>
        <a class="btn light" href="{{ route('admin.students.index') }}">Record a payment</a>
    </div>
</div>
<div class="grid">
    <div class="card stat"><span>Collected today</span><b>UGX {{ number_format($collectedToday) }}</b></div>
    <div class="card stat orange"><span>Present today</span><b>{{ number_format($presentToday) }}</b></div>
    <div class="card stat purple"><span>Subscribed now</span><b>{{ number_format($subscribed) }}</b></div>
    <div class="card stat slate"><span>Active students</span><b>{{ number_format($students) }}</b></div>
    <div class="card stat red"><span>Teachers</span><b>{{ number_format($teachers) }}</b></div>
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
@endsection
