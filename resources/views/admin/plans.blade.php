@extends('layouts.app')
@section('title', 'Plans and prices')
@section('content')
<h1>Plans and prices</h1>
<p class="muted">Changing a price affects new payments only. Students keep what they already paid for.</p>
<div class="card">
    <table>
        <tr><th>Plan</th><th>Lasts</th><th>Zoom sessions</th><th>Price (UGX)</th></tr>
        @foreach ($plans as $p)
            <tr>
                <td>{{ $p->name }} @unless ($p->active)<span class="pill">not offered</span>@endunless</td>
                <td>{{ $p->duration_days }} {{ $p->duration_days === 1 ? 'day' : 'days' }}</td>
                <td>{{ $p->sessions ?? 'unlimited' }}</td>
                <td>
                    @if (auth()->user()->isAdmin())
                        <form class="row" method="post" action="{{ route('admin.plans.update', $p) }}">@csrf @method('put')
                            <input name="price_ugx" type="number" min="0" value="{{ $p->price_ugx }}" style="width:120px">
                            <label style="display:inline"><input type="checkbox" name="active" value="1" @checked($p->active)> offered</label>
                            <button>Save</button>
                        </form>
                    @else
                        {{ number_format($p->price_ugx) }}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>
@endsection
