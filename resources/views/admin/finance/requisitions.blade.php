@extends('layouts.app')
@section('title', 'Requisitions')
@section('content')
<div class="card">
    <h2>Ask to spend money</h2>
    <form method="post" action="{{ route('requisitions.store') }}">@csrf
        <div class="row">
            <div><label>What for</label><input name="title" required maxlength="191"></div>
            <div><label>Amount (UGX)</label><input type="number" name="amount_ugx" min="1" required style="width:130px"></div>
            <div><label>Account</label><select name="account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->fullName() }}</option>@endforeach</select></div>
        </div>
        <p><label>Details</label><textarea name="details" rows="2" style="width:100%"></textarea></p>
        <button>Send for approval</button>
    </form>
</div>
<div class="card">
    <table>
        <tr><th>Asked</th><th>By</th><th>What for</th><th>UGX</th><th>Status</th></tr>
        @forelse ($requisitions as $r)
            <tr><td>{{ $r->created_at->timezone(config('school.timezone'))->format('j M Y') }}</td><td>{{ $r->requester->name }}</td>
                <td>{{ $r->title }}<br><span class="muted">{{ $r->account->fullName() }}{{ $r->details ? ' · '.$r->details : '' }}</span></td><td>{{ number_format($r->amount_ugx) }}</td>
                <td>@if ($canDecide && $r->status === 'pending')
                        <form class="inline" method="post" action="{{ route('requisitions.decide', $r) }}">@csrf<button name="action" value="approve">Approve</button> <button class="plain" name="action" value="decline">Decline</button></form>
                    @elseif ($canPay && $r->status === 'approved')
                        <form class="inline" method="post" action="{{ route('requisitions.decide', $r) }}">@csrf<button name="action" value="pay">Mark paid out</button></form>
                    @else<span @class(['pill', 'ok' => in_array($r->status, ['approved', 'paid'])])>{{ $r->status }}</span>@endif</td></tr>
        @empty<tr><td colspan="5" class="muted">No requisitions.</td></tr>@endforelse
    </table>
</div>
@endsection
