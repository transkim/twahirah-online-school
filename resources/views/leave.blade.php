@extends('layouts.app')
@section('title', 'Leave')
@section('content')
@unless (auth()->user()->isStudent())
<div class="card">
    <h2>Ask for leave</h2>
    <form class="row" method="post" action="{{ route('leave.store') }}">@csrf
        <div><label>From</label><input type="date" name="from_date" required></div>
        <div><label>To</label><input type="date" name="to_date" required></div>
        <div><label>Reason</label><input name="reason" required maxlength="191"></div>
        <button>Ask</button>
    </form>
    <p class="muted">While you are on leave, students cannot book live classes with you.</p>
</div>
@endunless
<div class="card">
    <h2>{{ $office ? 'Leave requests' : 'Your leave' }}</h2>
    <table>
        <tr>@if ($office)<th>Who</th>@endif<th>Days</th><th>Reason</th><th>Status</th></tr>
        @forelse ($requests as $l)
            <tr>@if ($office)<td>{{ $l->user->name }}</td>@endif
                <td>{{ $l->from_date->format('j M') }} – {{ $l->to_date->format('j M Y') }}</td><td>{{ $l->reason }}</td>
                <td>@if ($office && $l->status === 'pending')
                        <form class="inline" method="post" action="{{ route('leave.decide', $l) }}">@csrf<button name="status" value="approved">Approve</button> <button class="plain" name="status" value="declined">Decline</button></form>
                    @else<span @class(['pill', 'ok' => $l->status === 'approved'])>{{ $l->status }}</span>@endif</td></tr>
        @empty
            <tr><td colspan="4" class="muted">No leave requests.</td></tr>
        @endforelse
    </table>
</div>
@endsection
