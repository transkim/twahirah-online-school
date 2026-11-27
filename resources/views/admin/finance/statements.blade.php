@extends('layouts.app')
@section('title', 'Financial Statements')
@section('content')
<form class="row card" method="get">
    <div><label>From</label><input type="date" name="from" value="{{ $from }}"></div>
    <div><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
    <button>Show</button> <button type="button" class="warn-btn" onclick="window.print()">Print</button>
</form>
<div class="card">
    <h2>Income and spending, {{ \Illuminate\Support\Carbon::parse($from)->format('j M Y') }} to {{ \Illuminate\Support\Carbon::parse($to)->format('j M Y') }}</h2>
    <table>
        <tr><th colspan="2">Money in</th></tr>
        @forelse ($income as $r)<tr><td>{{ $r['account']->fullName() }}</td><td>{{ number_format($r['total']) }}</td></tr>@empty<tr><td class="muted" colspan="2">None</td></tr>@endforelse
        <tr><td><b>Total in</b></td><td><b>{{ number_format($income->sum('total')) }}</b></td></tr>
        <tr><th colspan="2">Money out</th></tr>
        @forelse ($expense as $r)<tr><td>{{ $r['account']->fullName() }}</td><td>{{ number_format($r['total']) }}</td></tr>@empty<tr><td class="muted" colspan="2">None</td></tr>@endforelse
        <tr><td><b>Total out</b></td><td><b>{{ number_format($expense->sum('total')) }}</b></td></tr>
        <tr><td><b>Surplus</b></td><td @class(['bad' => $income->sum('total') < $expense->sum('total')])><b>UGX {{ number_format($income->sum('total') - $expense->sum('total')) }}</b></td></tr>
    </table>
</div>
@endsection
