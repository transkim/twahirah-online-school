@extends('layouts.app')
@section('title', $fee['title'])
@section('content')
<div class="card">
    <h2>Record a {{ strtolower($fee['title']) === 'registration fees' ? 'registration' : 'certificate' }} fee</h2>
    <form class="row" method="post" action="{{ route('admin.finance.fees.store', $kind) }}">@csrf
        <div><label>Student number or phone</label><input name="login" required value="{{ old('login') }}"></div>
        <div><label>Amount (UGX)</label><input type="number" name="amount_ugx" min="1" value="{{ old('amount_ugx', $amount ?: '') }}" required style="width:130px"></div>
        <div><label>Date</label><input type="date" name="entry_date" value="{{ now(config('school.timezone'))->toDateString() }}" required></div>
        <button>Record</button>
    </form>
    <p class="muted">The usual amount is set in System Settings{{ $amount ? ': UGX '.number_format($amount) : '' }}.</p>
</div>
<div class="card">
    <table>
        <tr><th>Date</th><th>Student</th><th>UGX</th><th>By</th></tr>
        @forelse ($entries as $e)<tr><td>{{ $e->entry_date->format('j M Y') }}</td><td>{{ $e->student?->name }}</td><td>{{ number_format($e->amount_ugx) }}</td><td class="muted">{{ $e->recorder?->name }}</td></tr>
        @empty<tr><td class="muted">None recorded yet.</td></tr>@endforelse
    </table>
    {{ $entries->links() }}
</div>
@endsection
