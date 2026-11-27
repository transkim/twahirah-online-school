@extends('layouts.app')
@section('title', 'Student Levels')
@section('content')
<form class="row card" method="get">
    <div><label>Course</label><select name="course" onchange="this.form.submit()">@foreach ($courses as $c)<option value="{{ $c->id }}" @selected($course?->id === $c->id)>{{ $c->name }} ({{ $c->mode }})</option>@endforeach</select></div>
</form>
@if ($course)
<form class="card" method="post" action="{{ route('admin.student-levels.promote') }}">@csrf
    <h2>{{ $course->name }} ({{ $enrolments->count() }} students)</h2>
    <table>
        <tr><th><input type="checkbox" onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)"></th><th>Student</th><th>Level now</th></tr>
        @foreach ($enrolments as $e)<tr><td><input type="checkbox" class="pick" name="enrolments[]" value="{{ $e->id }}"></td><td>{{ $e->student->name }}</td><td>{{ $e->level?->name ?? '—' }}</td></tr>@endforeach
    </table>
    <div class="row" style="margin-top:12px">
        <div><label>Move the ticked students to</label><select name="course_level_id">@foreach ($course->levels as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
        <button>Move</button>
    </div>
</form>
@endif
@endsection
