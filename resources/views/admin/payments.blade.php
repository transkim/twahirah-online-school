@extends('layouts.app')
@section('title', 'Collections')
@section('content')
@php $tz = config('school.timezone'); $methods = ['mtn' => 'MTN', 'airtel' => 'Airtel', 'card' => 'Card', 'cash' => 'Cash', 'manual' => 'Office']; @endphp
<div class="card">
    <h2>Waiting for the office to confirm ({{ $pending->count() }})</h2>
    <table>
        <tr><th>Asked</th><th>Student</th><th>Plan</th><th>Amount</th><th>Via</th><th>Reference</th><th></th></tr>
        @forelse ($pending as $p)
            <tr><td>{{ $p->created_at->timezone($tz)->format('j M, H:i') }}</td>
                <td><a href="{{ route('admin.students.show', $p->student) }}">{{ $p->student->name }}</a></td>
                <td>{{ $p->plan?->name }}</td><td>UGX {{ number_format($p->amount_ugx) }}</td>
                <td>{{ $methods[$p->method] ?? $p->method }} {{ $p->phone }}</td><td class="muted">{{ $p->reference }}</td>
                <td style="white-space:nowrap">
                    <form class="inline" method="post" action="{{ route('admin.payments.confirm', $p) }}">@csrf<input name="provider_reference" placeholder="MoMo transaction id" style="width:150px"> <button>Money received</button></form>
                    <form class="inline" method="post" action="{{ route('admin.payments.reject', $p) }}">@csrf<button class="plain">Not received</button></form>
                </td></tr>
        @empty
            <tr><td colspan="7" class="muted">Nothing waiting.</td></tr>
        @endforelse
    </table>
</div>
<div class="card">
    <h2>Collected</h2>
    <form class="row" method="get">
        <div><label>From</label><input type="date" name="from" value="{{ $from }}"></div>
        <div><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
        <button>Show</button>
    </form>
    <div class="grid" style="margin-top:14px">
        <div class="card stat"><span>Total</span><b>UGX {{ number_format($total) }}</b></div>
        @foreach ($byMethod as $m)
            <div class="card stat slate"><span>{{ $methods[$m->method] ?? $m->method }}</span><b>UGX {{ number_format($m->total) }}</b><small>{{ $m->n }} payments</small></div>
        @endforeach
    </div>
    <table style="margin-top:14px">
        <tr><th>Paid</th><th>Student</th><th>Plan</th><th>Amount</th><th>Via</th><th>Reference</th></tr>
        @foreach ($recent as $p)
            <tr><td>{{ $p->paid_at->timezone($tz)->format('j M Y, H:i') }}</td><td>{{ $p->student->name }}</td><td>{{ $p->plan?->name }}</td><td>UGX {{ number_format($p->amount_ugx) }}</td><td>{{ $methods[$p->method] ?? $p->method }}</td><td class="muted">{{ $p->provider_reference ?? $p->reference }}</td></tr>
        @endforeach
    </table>
</div>
@endsection
