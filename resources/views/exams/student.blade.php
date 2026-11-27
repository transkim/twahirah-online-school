@extends('layouts.app')
@section('title', 'Exams')
@section('content')
<div class="card">
    <h2>Exams you can sit</h2>
    <table>
        <tr><th>Exam</th><th>Course</th><th>Time</th><th>Your best</th><th></th></tr>
        @forelse ($exams as $exam)
            @php $mine = $attempts->get($exam->id, collect()); $best = $mine->where('status', 'marked')->max('score'); $left = $exam->max_attempts === null ? null : max(0, $exam->max_attempts - $mine->count()); $open = $mine->firstWhere('status', 'in_progress'); @endphp
            <tr><td><b>{{ $exam->title }}</b>@if ($exam->instructions)<br><span class="muted">{{ \Illuminate\Support\Str::limit($exam->instructions, 120) }}</span>@endif</td>
                <td>{{ $exam->course->name }}{{ $exam->level ? ' · '.$exam->level->name : '' }}</td>
                <td>{{ $exam->minutes }} min · pass {{ $exam->pass_mark }}%</td>
                <td>{{ $best !== null ? $best.'%' : '—' }} @if ($mine->firstWhere('status', 'waiting_for_teacher'))<span class="pill">waiting for teacher</span>@endif</td>
                <td>@if ($open)<a class="btn" href="{{ route('exams.attempt', $open) }}">Carry on</a>
                    @elseif ($left === 0)<span class="muted">No attempts left</span>
                    @else<form class="inline" method="post" action="{{ route('exams.start', $exam) }}">@csrf<button>{{ $mine->isEmpty() ? 'Start' : 'Retake' }}</button></form>
                        @if ($left !== null)<br><span class="muted">{{ $left }} attempts left</span>@endif
                    @endif</td></tr>
        @empty
            <tr><td colspan="5" class="muted">No exams open for your courses right now.</td></tr>
        @endforelse
    </table>
</div>
<div class="card">
    <h2>Past attempts</h2>
    <table>
        @forelse ($attempts->flatten() as $a)
            <tr><td><a href="{{ route('exams.attempt', $a) }}">{{ $a->exam->title }}</a></td><td class="muted">{{ $a->started_at->timezone(auth()->user()->timezone)->format('j M Y, H:i') }}</td>
                <td>{{ $a->score !== null ? $a->score.'%' : str_replace('_', ' ', $a->status) }} @if ($a->passed)<span class="pill ok">passed</span>@endif</td></tr>
        @empty
            <tr><td class="muted">None yet.</td></tr>
        @endforelse
    </table>
</div>
@if ($certificates->isNotEmpty())
    <div class="card">
        <h2>Your certificates</h2>
        <table>@foreach ($certificates as $c)<tr><td><a href="{{ route('certificates.show', $c) }}">{{ $c->course->name }}{{ $c->level ? ' · '.$c->level->name : '' }}</a></td><td class="muted">{{ $c->issued_at->format('j M Y') }}</td></tr>@endforeach</table>
    </div>
@endif
@endsection
