@extends('layouts.app')
@section('title', $exam->title)
@section('content')
<div class="card">
    <h2>{{ $exam->title }}</h2>
    <p class="muted">{{ $exam->course->name }} ({{ $exam->course->mode }}){{ $exam->level ? ' · '.$exam->level->name : '' }}</p>
    <form class="row" method="post" action="{{ route('exams.update', $exam) }}">@csrf @method('put')
        <div><label>Minutes</label><input type="number" name="minutes" value="{{ $exam->minutes }}" min="1" max="600" style="width:90px"></div>
        <div><label>Pass mark %</label><input type="number" name="pass_mark" value="{{ $exam->pass_mark }}" min="0" max="100" style="width:90px"></div>
        <div><label>Attempts</label><input type="number" name="max_attempts" value="{{ $exam->max_attempts }}" min="1" max="50" style="width:90px" placeholder="any"></div>
        <div><label>Questions per attempt</label><input type="number" name="questions_per_attempt" value="{{ $exam->questions_per_attempt }}" min="1" style="width:110px" placeholder="all"></div>
        <div><label><input type="checkbox" name="gives_certificate" value="1" @checked($exam->gives_certificate)> Certificate on pass</label></div>
        <div><label><input type="checkbox" name="published" value="1" @checked($exam->published)> Open to students</label></div>
        <button>Save</button>
    </form>
</div>
<div class="card">
    <h2>Question bank ({{ $exam->questions->count() }}, {{ $exam->questions->sum('points') }} points)</h2>
    <table>
        @forelse ($exam->questions as $i => $q)
            <tr><td>{{ $i + 1 }}.</td><td>{!! nl2br(e($q->prompt)) !!}
                @if ($q->type === 'choice')<ol style="margin:4px 0 0 18px">@foreach ($q->options as $n => $o)<li @class(['warn' => $n === $q->correct_option])>{{ $o }}{{ $n === $q->correct_option ? ' ✓' : '' }}</li>@endforeach</ol>
                @else<br><span class="muted">Written or recorded answer, marked by the teacher</span>@endif</td>
                <td class="muted">{{ $q->points }} pt</td>
                <td><form class="inline" method="post" action="{{ route('exams.questions.destroy', [$exam, $q]) }}">@csrf @method('delete')<button class="plain">Remove</button></form></td></tr>
        @empty
            <tr><td class="muted">No questions yet.</td></tr>
        @endforelse
    </table>
    <form method="post" action="{{ route('exams.questions.store', $exam) }}" style="margin-top:14px">@csrf
        <div class="row">
            <div><label>Type</label><select name="type"><option value="choice">Multiple choice (marked automatically)</option><option value="written">Written or recited (teacher marks)</option></select></div>
            <div><label>Points</label><input type="number" name="points" value="1" min="1" max="100" style="width:80px"></div>
        </div>
        <p><label>Question</label><textarea name="prompt" rows="2" required style="width:100%">{{ old('prompt') }}</textarea></p>
        <div class="row">
            <div style="flex:1"><label>Answers, one per line (multiple choice)</label><textarea name="options" rows="4" style="width:100%">{{ old('options') }}</textarea></div>
            <div><label>Number of the right answer</label><input type="number" name="correct_option" min="1" value="{{ old('correct_option') }}" style="width:90px"></div>
        </div>
        <p style="margin-top:10px"><button>Add question</button></p>
    </form>
</div>
<div class="card">
    <h2>Attempts ({{ $attempts->count() }})</h2>
    <table>
        @forelse ($attempts as $a)
            <tr><td>{{ $a->student->name }}</td><td class="muted">{{ $a->started_at->timezone(auth()->user()->timezone)->format('j M, H:i') }}</td>
                <td>{{ $a->score !== null ? $a->score.'%' : '' }} <span @class(['pill', 'ok' => $a->passed])>{{ $a->passed ? 'passed' : str_replace('_', ' ', $a->status) }}</span></td>
                <td><a href="{{ route('exams.attempt', $a) }}">{{ $a->status === 'waiting_for_teacher' ? 'Mark' : 'Open' }}</a></td></tr>
        @empty
            <tr><td class="muted">Nobody has sat this exam yet.</td></tr>
        @endforelse
    </table>
</div>
@endsection
