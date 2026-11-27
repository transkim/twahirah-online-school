@extends('layouts.app')
@section('title', 'Cancelled Enrolments')
@section('content')
<div class="card">
    <table>
        <tr><th>Student</th><th>Course</th><th>Teacher</th><th>Ended</th><th>Last seen</th></tr>
        @forelse ($enrolments as $e)
            <tr><td><a href="{{ route('admin.students.show', $e->student) }}">{{ $e->student->name }}</a></td><td>{{ $e->course->name }} ({{ $e->course->mode }})</td><td>{{ $e->teacher?->name }}</td>
                <td>{{ $e->ended_on ?? '—' }}</td><td class="muted">{{ $e->last_seen_on ?? '—' }}</td></tr>
        @empty
            <tr><td class="muted">No cancelled enrolments.</td></tr>
        @endforelse
    </table>
    {{ $enrolments->links() }}
</div>
@endsection
