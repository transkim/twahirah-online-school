@extends('layouts.app')
@section('title', $attempt->exam->title)
@section('content')
<div class="card">
    <h2>{{ $attempt->exam->title }} · {{ $attempt->student->name }}</h2>
    @if ($attempt->status === 'marked')
        <p style="font-size:22px"><b>{{ $attempt->score }}%</b> ({{ $attempt->points }} of {{ $attempt->max_points }} points)
            @if ($attempt->passed)<span class="pill ok">passed</span>@else<span class="pill">not passed yet</span>@endif</p>
        @if ($certificate)<p><a class="btn" href="{{ route('certificates.show', $certificate) }}">Open certificate</a></p>@endif
    @elseif ($attempt->status === 'waiting_for_teacher')
        <p class="warn">Waiting for the teacher to mark the written answers.</p>
    @else
        <p class="muted">Not handed in yet.</p>
    @endif
</div>
<form method="post" action="{{ route('exams.mark', $attempt) }}">@csrf
@foreach ($questions as $i => $q)
    @php $a = $answers->get($q->id); @endphp
    <div class="card">
        <p><b>{{ $i + 1 }}.</b> {!! nl2br(e($q->prompt)) !!}</p>
        @if ($q->type === 'choice')
            <p>Answer: {{ $a && $a->choice !== null ? ($q->options[$a->choice] ?? '') : 'none' }}
                @if ($a && $a->choice === $q->correct_option) <span class="pill ok">right</span> @else <span class="pill">wrong</span> · right answer: {{ $q->options[$q->correct_option] ?? '' }} @endif</p>
        @else
            @if ($a?->text)<p>{!! nl2br(e($a->text)) !!}</p>@endif
            @if ($a?->attachment_path)<audio controls preload="none" src="{{ route('exams.answer.file', [$attempt, $a->id]) }}"></audio>@endif
            @if ($canMark && $a)
                <p><label>Points (out of {{ $q->points }})</label><input type="number" name="points[{{ $a->id }}]" min="0" max="{{ $q->points }}" value="{{ $a->points_awarded }}" style="width:90px"></p>
            @else
                <p class="muted">{{ $a?->points_awarded !== null ? $a->points_awarded.' of '.$q->points.' points' : 'Not marked yet' }}</p>
            @endif
        @endif
    </div>
@endforeach
@if ($canMark && $questions->contains('type', 'written'))<button>Save marks</button>@endif
</form>
@endsection
