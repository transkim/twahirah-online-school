@extends('layouts.app')
@section('title', 'Course Lists')
@section('content')
<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:16px">
    <h1 style="margin:0">Courses</h1>
    <span class="muted">{{ $courses->count() }} courses · Chat classes and Zoom classes</span>
</div>
<div class="card">
    <table>
        <tr><th>Course</th><th>Class type</th><th>Levels</th><th>Teachers</th><th>Students now</th></tr>
        @forelse ($courses as $course)
            <tr>
                <td><b>{{ $course->name }}</b>@if ($course->code) <span class="muted">{{ $course->code }}</span>@endif</td>
                <td><span @class(['pill', 'ok' => $course->mode === 'zoom'])>{{ $course->mode === 'zoom' ? 'Zoom' : 'Chat' }}</span></td>
                <td>{{ $course->levels->pluck('name')->join(', ') ?: '—' }}</td>
                <td>{{ number_format($course->teachers) }}</td>
                <td>{{ number_format($course->active_students) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No courses yet.</td></tr>
        @endforelse
    </table>
</div>
@endsection
