@extends('layouts.app')
@section('title', 'Marks')
@section('content')
<form class="row card" method="get">
    <div><label>Course</label><select name="course" onchange="this.form.submit()">@foreach ($courses as $c)<option value="{{ $c->id }}" @selected($course?->id === $c->id)>{{ $c->name }} ({{ $c->mode }})</option>@endforeach</select></div>
</form>
@if ($course)
    <div class="card">
        <h2>Enter marks · {{ $course->name }} ({{ $enrolments->count() }} students)</h2>
        <form method="post" action="{{ route('marks.store') }}">@csrf
            <input type="hidden" name="course_id" value="{{ $course->id }}">
            <div class="row"><div><label>What for</label><input name="title" required placeholder="For example: Monthly test"></div>
                <div><label>Date</label><input type="date" name="given_on" value="{{ now(config('school.timezone'))->toDateString() }}" required></div></div>
            <table style="margin-top:10px">
                <tr><th>Student</th><th>Level</th><th>Mark /100</th><th></th></tr>
                @foreach ($enrolments as $e)
                    <tr><td>{{ $e->student->name }}</td><td class="muted">{{ $e->level?->name }}</td>
                        <td><input type="number" name="scores[{{ $e->student_id }}]" min="0" max="100" style="width:90px"></td>
                        <td><a href="{{ route('students.report-card', $e->student) }}">Report card</a></td></tr>
                @endforeach
            </table>
            <p style="margin-top:10px"><button>Save marks</button></p>
        </form>
    </div>
    <div class="card">
        <h2>Latest marks</h2>
        <table>@forelse ($recent as $m)<tr><td>{{ $m->given_on->format('j M Y') }}</td><td>{{ $m->student->name }}</td><td>{{ $m->title }}</td><td class="muted">{{ $m->kind }}</td><td>{{ $m->score }}%</td></tr>@empty<tr><td class="muted">No marks yet.</td></tr>@endforelse</table>
    </div>
@endif
@endsection
