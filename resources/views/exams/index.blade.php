@extends('layouts.app')
@section('title', 'Exams')
@section('content')
<div class="card">
    <h2>Exams</h2>
    <table>
        <tr><th>Exam</th><th>Course</th><th>Questions</th><th>Attempts</th><th>Status</th></tr>
        @forelse ($exams as $exam)
            <tr><td><a href="{{ route('exams.show', $exam) }}"><b>{{ $exam->title }}</b></a></td><td>{{ $exam->course->name }} ({{ $exam->course->mode }}){{ $exam->level ? ' · '.$exam->level->name : '' }}</td>
                <td>{{ $exam->questions_count }}</td><td>{{ $exam->attempts_count }}</td><td><span @class(['pill', 'ok' => $exam->published])>{{ $exam->published ? 'open' : 'hidden' }}</span></td></tr>
        @empty
            <tr><td colspan="5" class="muted">No exams yet.</td></tr>
        @endforelse
    </table>
</div>
@if ($courses->isNotEmpty())
    <div class="card">
        <h2>Make an exam</h2>
        <form method="post" action="{{ route('exams.store') }}">@csrf
            <div class="row">
                @include('learning._course-picker')
                <div><label>Minutes</label><input type="number" name="minutes" value="30" min="1" max="600" style="width:90px"></div>
                <div><label>Pass mark %</label><input type="number" name="pass_mark" value="50" min="0" max="100" style="width:90px"></div>
                <div><label>Attempts (empty = unlimited)</label><input type="number" name="max_attempts" min="1" max="50" style="width:120px"></div>
            </div>
            <p><label>Title</label><input name="title" required maxlength="191" style="width:100%" placeholder="For example: Tajweed level 1 final exam"></p>
            <p><label>Instructions</label><textarea name="instructions" rows="3" style="width:100%"></textarea></p>
            <button>Make exam</button>
        </form>
    </div>
@endif
@endsection
