@extends('layouts.app')
@section('title', 'Graduates')
@section('content')
<div class="card">
    <table>
        <tr><th>Student</th><th>Course and level</th><th>Score</th><th>Date</th><th></th></tr>
        @forelse ($certificates as $c)
            <tr><td><a href="{{ route('admin.students.show', $c->student) }}">{{ $c->student->name }}</a></td><td>{{ $c->course->name }}{{ $c->level ? ' · '.$c->level->name : '' }}</td>
                <td>{{ $c->score !== null ? $c->score.'%' : '' }}</td><td>{{ $c->issued_at->format('j M Y') }}</td><td><a href="{{ route('certificates.show', $c) }}">Certificate</a></td></tr>
        @empty
            <tr><td class="muted">No certificates yet. Students get one when they pass an exam.</td></tr>
        @endforelse
    </table>
    {{ $certificates->links() }}
</div>
@endsection
