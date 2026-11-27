@extends('layouts.app')
@section('title', 'Calendar')
@section('content')
@php $colors = ['class' => 'ok', 'event' => '', 'leave' => '']; @endphp
<div class="card row" style="justify-content:space-between;align-items:center">
    <a class="btn light" href="{{ route('admin.calendar', ['week' => $start->copy()->subWeek()->toDateString()]) }}">‹ Week before</a>
    <b>{{ $start->format('j M') }} – {{ $start->copy()->addDays(6)->format('j M Y') }}</b>
    <a class="btn light" href="{{ route('admin.calendar', ['week' => $start->copy()->addWeek()->toDateString()]) }}">Next week ›</a>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(220px, 1fr))">
    @foreach ($days as $date => $items)
        @php $d = \Illuminate\Support\Carbon::parse($date); @endphp
        <div class="card" style="margin:0">
            <h2 @class(['warn' => $d->isToday()])>{{ $d->format('l j M') }}</h2>
            @forelse ($items as $i)
                <p style="margin:6px 0"><span @class(['pill', 'ok' => $i['kind'] === 'class'])>{{ $i['kind'] === 'class' ? 'class' : ($i['kind'] === 'event' ? 'event' : 'leave') }}</span>
                    <b>{{ $i['time'] }}</b> {{ $i['text'] }}</p>
            @empty
                <p class="muted">Nothing booked.</p>
            @endforelse
        </div>
    @endforeach
</div>
@endsection
