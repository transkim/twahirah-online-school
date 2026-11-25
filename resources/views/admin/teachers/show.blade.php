@extends('layouts.app')
@section('title', $teacher->name)
@section('content')
@php $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; @endphp
<h1>{{ $teacher->name }}</h1>
<div class="grid">
    <div class="card">
        <h2>Courses this teacher teaches</h2>
        <table>
            @forelse ($teacher->teacherCourses as $tc)
                <tr><td>{{ $tc->course->name }} <span class="muted">({{ $tc->course->mode }})</span> {{ $tc->course_level_id ? '· one level only' : '· all levels' }}</td>
                    <td><form class="inline" method="post" action="{{ route('admin.teachers.courses.destroy', [$teacher, $tc]) }}">@csrf @method('delete')<button class="plain">Remove</button></form></td></tr>
            @empty
                <tr><td class="muted">None yet. New students are only given to teachers listed for their course.</td></tr>
            @endforelse
        </table>
        <form class="row" method="post" action="{{ route('admin.teachers.courses.store', $teacher) }}" style="margin-top:12px">@csrf
            <select name="course_id">@foreach ($courses as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->mode }})</option>@endforeach</select>
            <button>Add course</button>
        </form>
    </div>
    <div class="card">
        <h2>Free hours for live classes</h2>
        <p class="muted">Times are Kampala time. Students can book classes in these hours.</p>
        <table>
            @forelse ($teacher->availabilities->sortBy(['weekday', 'start_time']) as $a)
                <tr><td>{{ $days[$a->weekday] }}</td><td>{{ substr($a->start_time, 0, 5) }}–{{ substr($a->end_time, 0, 5) }}</td>
                    <td><form class="inline" method="post" action="{{ route('admin.teachers.availability.destroy', [$teacher, $a]) }}">@csrf @method('delete')<button class="plain">Remove</button></form></td></tr>
            @empty
                <tr><td class="muted">No free hours set.</td></tr>
            @endforelse
        </table>
        <form class="row" method="post" action="{{ route('admin.teachers.availability.store', $teacher) }}" style="margin-top:12px">@csrf
            <select name="weekday">@foreach ($days as $i => $d)<option value="{{ $i }}">{{ $d }}</option>@endforeach</select>
            <input type="time" name="start_time" required> <input type="time" name="end_time" required>
            <button>Add</button>
        </form>
    </div>
</div>
<div class="card">
    <h2>Students ({{ $students->count() }})</h2>
    <table>
        @forelse ($students as $e)
            <tr><td><a href="{{ route('admin.students.show', $e->student) }}">{{ $e->student->name }}</a></td><td>{{ $e->course->name }} ({{ $e->course->mode }})</td><td class="muted">{{ $e->level?->name }}</td></tr>
        @empty
            <tr><td class="muted">No students yet.</td></tr>
        @endforelse
    </table>
</div>
@endsection
