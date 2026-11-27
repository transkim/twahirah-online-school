@extends('layouts.app')
@section('title', 'Fee Discounts')
@section('content')
<div class="card">
    <h2>Give a discount</h2>
    <form class="row" method="post" action="{{ route('admin.finance.discounts.store') }}">@csrf
        <div><label>Student number or phone</label><input name="login" required value="{{ old('login') }}"></div>
        <div><label>Percent off</label><input type="number" name="percent" min="1" max="100" required style="width:90px"></div>
        <div><label>Reason</label><input name="reason" required placeholder="Scholarship, staff child, siblings"></div>
        <div><label>Until (empty = always)</label><input type="date" name="ends_on"></div>
        <button>Give discount</button>
    </form>
    <p class="muted">A family phone number gives the discount to every student on it. It comes off every plan they pay for.</p>
</div>
<div class="card">
    <table>
        <tr><th>Student</th><th>Off</th><th>Reason</th><th>Until</th><th></th></tr>
        @forelse ($discounts as $d)
            <tr><td>{{ $d->student->name }}</td><td>{{ $d->percent }}%</td><td>{{ $d->reason }}</td><td>{{ $d->ends_on?->format('j M Y') ?? 'always' }}</td>
                <td><form class="inline" method="post" action="{{ route('admin.finance.discounts.destroy', $d) }}">@csrf @method('delete')<button class="plain">Remove</button></form></td></tr>
        @empty<tr><td class="muted">No discounts.</td></tr>@endforelse
    </table>
    {{ $discounts->links() }}
</div>
@endsection
