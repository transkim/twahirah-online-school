@extends('layouts.app')
@section('title', 'Subscription and fees')
@section('content')
@php $tz = auth()->user()->timezone; $cycles = ['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year_12m' => 'Year (12 months)', 'year_52w' => 'Year (52 weeks)']; @endphp
@if ($owing > 0)
    <div class="flash errors">You have an old balance of <b>UGX {{ number_format($owing) }}</b> from before the new system. Please clear it with the office.</div>
@endif
<div class="card">
    <h2>Your subscriptions</h2>
    <table>
        <tr><th>Plan</th><th>From</th><th>Until</th><th>Zoom sessions</th><th>Status</th></tr>
        @forelse ($subscriptions as $s)
            <tr><td>{{ $s->plan->name }}</td><td>{{ $s->starts_at->timezone($tz)->format('j M Y') }}</td><td>{{ $s->ends_at->timezone($tz)->format('j M Y') }}</td>
                <td>{{ $s->sessions_total === null ? '' : $s->sessionsLeft().' of '.$s->sessions_total.' left' }}</td>
                <td><span @class(['pill', 'ok' => $s->isUsable()])>{{ $s->isUsable() ? 'active' : ($s->starts_at->isFuture() ? 'starts later' : 'ended') }}</span></td></tr>
        @empty
            <tr><td colspan="5" class="muted">No subscriptions yet.</td></tr>
        @endforelse
    </table>
</div>

<div class="card">
    <h2>Subscribe or renew</h2>
    <form method="post" action="{{ route('my.subscribe.store') }}">@csrf
        @foreach ($plans as $group => $list)
            <h3 style="margin:14px 0 6px">{{ $group }}</h3>
            <div class="grid">
                @foreach ($list as $plan)
                    <label class="card" style="margin:0;cursor:pointer;text-transform:none;letter-spacing:0;font-size:14px;color:var(--ink)">
                        <input type="radio" name="plan_id" value="{{ $plan->id }}" required @checked(old('plan_id') == $plan->id)>
                        <b>{{ $cycles[$plan->cycle] ?? $plan->cycle }}</b><br>UGX {{ number_format($plan->price_ugx) }}
                        @if ($plan->sessions)<br><span class="muted">{{ $plan->sessions }} Zoom sessions</span>@endif
                    </label>
                @endforeach
            </div>
        @endforeach
        <div class="row" style="margin-top:16px">
            <div><label>Pay with</label><select name="method"><option value="mtn">MTN Mobile Money</option><option value="airtel">Airtel Money</option><option value="card">Card (students abroad)</option></select></div>
            <div><label>Mobile Money number</label><input name="phone" value="{{ old('phone', auth()->user()->phone) }}" placeholder="0700 000 000"></div>
            <button>Pay</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Payments and receipts</h2>
    <table>
        <tr><th>When</th><th>Plan</th><th>Amount</th><th>Status</th><th></th></tr>
        @forelse ($payments as $p)
            <tr><td>{{ $p->created_at->timezone($tz)->format('j M Y, H:i') }}</td><td>{{ $p->plan?->name }}</td><td>UGX {{ number_format($p->amount_ugx) }}</td>
                <td><span @class(['pill', 'ok' => $p->status === 'paid'])>{{ $p->status }}</span></td>
                <td><a href="{{ route('my.payments.show', $p) }}">{{ $p->status === 'paid' ? 'Receipt' : 'How to pay' }}</a></td></tr>
        @empty
            <tr><td colspan="5" class="muted">No payments yet.</td></tr>
        @endforelse
    </table>
</div>
@endsection
