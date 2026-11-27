@extends('layouts.app')
@section('title', 'Account Statement')
@section('content')
@php $tz = config('school.timezone'); @endphp
<form class="row card" method="get"><input name="q" value="{{ $search }}" placeholder="Student name, number or phone" required><button>Find student</button></form>
@if ($matches->isNotEmpty())
    <div class="card"><table>@foreach ($matches as $m)<tr><td><a href="{{ route('admin.reports.statement', ['student' => $m->id]) }}">{{ $m->name }}</a></td><td class="muted">{{ $m->student_number }} {{ $m->phone }}</td></tr>@endforeach</table></div>
@endif
@if ($student)
    <div class="card">
        <h2>{{ $student->name }} {{ $student->student_number ? '· '.$student->student_number : '' }} <button type="button" class="warn-btn" style="float:right" onclick="window.print()">Print</button></h2>
        <h3>Payments</h3>
        <table>
            <tr><th>Date</th><th>For</th><th>Via</th><th>Reference</th><th>Status</th><th>UGX</th></tr>
            @foreach ($payments as $p)<tr><td>{{ $p->created_at->timezone($tz)->format('j M Y') }}</td><td>{{ $p->plan?->name }}</td><td>{{ strtoupper($p->method) }}</td><td class="muted">{{ $p->provider_reference ?? $p->reference }}</td><td>{{ $p->status }}</td><td>{{ number_format($p->amount_ugx) }}</td></tr>@endforeach
            <tr><td colspan="5"><b>Total paid</b></td><td><b>{{ number_format($payments->where('status', 'paid')->sum('amount_ugx')) }}</b></td></tr>
        </table>
        <h3>Subscriptions</h3>
        <table>@foreach ($subscriptions as $s)<tr><td>{{ $s->plan->name }}</td><td>{{ $s->starts_at->timezone($tz)->format('j M Y') }} – {{ $s->ends_at->timezone($tz)->format('j M Y') }}</td><td class="muted">{{ $s->note }}</td><td>{{ $s->sessions_total !== null ? $s->sessions_used.'/'.$s->sessions_total.' sessions used' : '' }}</td></tr>@endforeach</table>
        <h3>Old portal balances</h3>
        <table>@foreach ($enrolments as $e)<tr><td>{{ $e->course->name }} ({{ $e->course->mode }})</td><td @class(['bad' => $e->legacy_balance_ugx < 0])>{{ number_format($e->legacy_balance_ugx) }}</td></tr>@endforeach</table>
    </div>
@endif
@endsection
