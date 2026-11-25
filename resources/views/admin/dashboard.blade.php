@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<h1>Dashboard</h1>
<div class="grid">
    <div class="card stat"><b>{{ number_format($students) }}</b><span>active students</span></div>
    <div class="card stat"><b>{{ number_format($subscribed) }}</b><span>with a subscription now</span></div>
    <div class="card stat"><b>{{ number_format($presentToday) }}</b><span>marked present today</span></div>
    <div class="card stat"><b>UGX {{ number_format($collectedToday) }}</b><span>collected today</span></div>
    <div class="card stat"><b>{{ number_format($teachers) }}</b><span>teachers</span></div>
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
