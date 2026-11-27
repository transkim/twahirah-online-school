@extends('layouts.app')
@section('title', 'Students')
@section('content')
<div class="row" style="justify-content:space-between;margin-bottom:16px">
    <h1 style="margin:0">Students</h1>
    <a href="{{ route('admin.students.create') }}">Add a student</a>
</div>
<form class="row" style="margin-bottom:16px"><input name="q" value="{{ $search }}" placeholder="Name, phone or student number"> <button>Search</button></form>
<div class="card">
    <table>
        <tr><th>Student</th><th>Phone</th><th>Courses</th><th>Subscription</th></tr>
        @forelse ($students as $s)
            <tr>
                <td><a href="{{ route('admin.students.show', $s) }}">{{ $s->name }}</a> <span class="muted">{{ $s->student_number }}</span></td>
                <td>{{ $s->phone }}</td>
                <td>{{ $s->enrolments->map(fn ($e) => $e->course->name.' ('.$e->course->mode.')')->join(', ') ?: '—' }}</td>
                <td>@if ($s->subscriptions->isNotEmpty())<span class="pill ok">until {{ $s->subscriptions->max('ends_at')->format('j M') }}</span>@else<span class="warn">none</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No students found.</td></tr>
        @endforelse
    </table>
    {{ $students->links() }}
</div>
@endsection
