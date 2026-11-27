@extends('layouts.app')
@section('title', 'Report card')
@section('content')
<style>@media print { .side, .top, .titlebar, form.noprint, .noprint { display:none !important; } .shell { display:block; } }</style>
<form class="row noprint card" method="get">
    <div><label>From</label><input type="date" name="from" value="{{ $from }}"></div>
    <div><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
    <button>Show</button>
    <button type="button" onclick="window.print()" class="warn-btn">Print</button>
</form>
<div class="card">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:10px"><img src="{{ asset('images/logo.png') }}" alt="" style="height:56px">
        <div><b style="font-size:18px">{{ config('school.name') }}</b><br>Report card · {{ \Illuminate\Support\Carbon::parse($from)->format('j M Y') }} to {{ \Illuminate\Support\Carbon::parse($to)->format('j M Y') }}</div></div>
    <p><b>{{ $student->name }}</b> {{ $student->student_number ? '· '.$student->student_number : '' }}</p>
    <table>
        <tr><th>Course</th><th>Teacher</th>@foreach ($weights as $kind => $w)<th>{{ ucfirst($kind) }} ({{ $w }}%)</th>@endforeach<th>Total</th><th>Grade</th><th>Days present</th></tr>
        @forelse ($rows as $r)
            <tr><td>{{ $r['course'] }}{{ $r['level'] ? ' · '.$r['level'] : '' }}</td><td>{{ $r['teacher'] }}</td>
                @foreach ($weights as $kind => $w)<td>{{ $r['averages'][$kind] ?? '—' }}</td>@endforeach
                <td><b>{{ $r['total'] ?? '—' }}</b></td><td><b>{{ $r['grade'] }}</b> <span class="muted">{{ $r['comment'] }}</span></td><td>{{ $r['present'] }}</td></tr>
        @empty
            <tr><td colspan="8" class="muted">No marks or attendance in these dates.</td></tr>
        @endforelse
    </table>
</div>
@foreach ($rows->filter(fn ($r) => $r['marks']->isNotEmpty()) as $r)
    <div class="card">
        <h2>{{ $r['course'] }}: every mark</h2>
        <table>@foreach ($r['marks'] as $m)<tr><td>{{ $m->given_on->format('j M Y') }}</td><td>{{ $m->title }}</td><td class="muted">{{ $m->kind }}</td><td>{{ $m->score }}%</td></tr>@endforeach</table>
    </div>
@endforeach
@if ($certificates->isNotEmpty())
    <div class="card"><h2>Certificates</h2>
        <table>@foreach ($certificates as $c)<tr><td><a href="{{ route('certificates.show', $c) }}">{{ $c->course->name }}{{ $c->level ? ' · '.$c->level->name : '' }}</a></td><td class="muted">{{ $c->issued_at->format('j M Y') }}</td></tr>@endforeach</table></div>
@endif
@endsection
