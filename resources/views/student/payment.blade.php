@extends('layouts.app')
@section('title', $payment->status === 'paid' ? 'Receipt' : 'Payment waiting')
@section('content')
@php $tz = auth()->user()->timezone; @endphp
<div class="card" style="max-width:560px">
    <h2>{{ $payment->status === 'paid' ? 'Receipt' : 'Payment waiting' }}</h2>
    <table>
        <tr><th>School</th><td>{{ config('school.name') }}</td></tr>
        <tr><th>Student</th><td>{{ $payment->student->name }} {{ $payment->student->student_number ? '('.$payment->student->student_number.')' : '' }}</td></tr>
        <tr><th>Plan</th><td>{{ $payment->plan?->name }}</td></tr>
        <tr><th>Amount</th><td><b>UGX {{ number_format($payment->amount_ugx) }}</b></td></tr>
        <tr><th>Paid by</th><td>{{ ['mtn' => 'MTN Mobile Money', 'airtel' => 'Airtel Money', 'card' => 'Card', 'cash' => 'Cash', 'manual' => 'Office'][$payment->method] ?? $payment->method }}</td></tr>
        <tr><th>Reference</th><td>{{ $payment->reference }}</td></tr>
        <tr><th>Status</th><td><span @class(['pill', 'ok' => $payment->status === 'paid'])>{{ $payment->status }}</span>
            @if ($payment->paid_at) on {{ $payment->paid_at->timezone($tz)->format('j M Y, H:i') }} @endif</td></tr>
    </table>
    <p style="margin-top:14px"><a class="btn light" href="{{ route('my.subscribe') }}">Back to subscription</a></p>
</div>
@endsection
