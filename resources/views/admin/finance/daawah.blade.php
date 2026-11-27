@extends('layouts.app')
@section('title', 'Daawah')
@section('content')
<div class="grid">
    <div class="card stat"><span>Received</span><b>UGX {{ number_format($in) }}</b></div>
    <div class="card stat red"><span>Spent</span><b>UGX {{ number_format($out) }}</b></div>
    <div class="card stat slate"><span>Balance</span><b>UGX {{ number_format($in - $out) }}</b></div>
</div>
<div class="card" style="margin-top:18px">
    <h2>Record Daawah money</h2>
    @include('admin.finance._entry-form')
    <table style="margin-top:14px">
        <tr><th>Date</th><th>Account</th><th>What for</th><th>UGX</th></tr>
        @forelse ($entries as $e)<tr><td>{{ $e->entry_date->format('j M Y') }}</td><td>{{ $e->account->fullName() }}</td><td>{{ $e->description }}</td><td @class(['bad' => $e->account->type === 'expense'])>{{ $e->account->type === 'expense' ? '-' : '' }}{{ number_format($e->amount_ugx) }}</td></tr>
        @empty<tr><td class="muted">Nothing recorded yet.</td></tr>@endforelse
    </table>
</div>
@endsection
