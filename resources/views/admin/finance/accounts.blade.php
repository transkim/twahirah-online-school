@extends('layouts.app')
@section('title', 'Accounts')
@section('content')
<div class="split">
    <div class="card">
        <h2>Accounts and sub-accounts</h2>
        <table>
            <tr><th>Account</th><th>Type</th><th>Total recorded (UGX)</th></tr>
            @foreach ($accounts as $a)
                <tr><td><b>{{ $a->name }}</b></td><td><span @class(['pill', 'ok' => $a->type === 'income'])>{{ $a->type === 'income' ? 'money in' : 'money out' }}</span></td><td>{{ number_format($totals[$a->id] ?? 0) }}</td></tr>
                @foreach ($a->children as $c)<tr><td style="padding-left:28px">› {{ $c->name }}</td><td></td><td>{{ number_format($totals[$c->id] ?? 0) }}</td></tr>@endforeach
            @endforeach
        </table>
    </div>
    <div class="card">
        <h2>Add an account</h2>
        <form method="post" action="{{ route('admin.finance.accounts.store') }}">@csrf
            <p><label>Name</label><input name="name" required style="width:100%"></p>
            <p><label>Type</label><select name="type"><option value="income">Money in</option><option value="expense">Money out</option></select></p>
            <p><label>Inside account (for a sub-account)</label><select name="parent_id"><option value="">None</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->type }})</option>@endforeach</select></p>
            <button>Add</button>
        </form>
    </div>
</div>
<div class="card">
    <h2>Record money in or out</h2>
    @include('admin.finance._entry-form', ['accounts' => $all])
    <table style="margin-top:14px">
        <tr><th>Date</th><th>Account</th><th>What for</th><th>UGX</th><th>By</th></tr>
        @foreach ($entries as $e)<tr><td>{{ $e->entry_date->format('j M Y') }}</td><td>{{ $e->account->fullName() }}</td><td>{{ $e->description }}</td><td @class(['bad' => $e->account->type === 'expense'])>{{ $e->account->type === 'expense' ? '-' : '' }}{{ number_format($e->amount_ugx) }}</td><td class="muted">{{ $e->recorder?->name ?? 'system' }}</td></tr>@endforeach
    </table>
</div>
@endsection
