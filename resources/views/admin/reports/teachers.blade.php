@extends('layouts.app')
@section('title', 'Teacher Reports')
@section('content')
<div class="card">
    <h2>Last 30 days</h2>
    <table>
        <tr><th>Teacher</th><th>Students now</th><th>Live classes held</th><th>Student attendance marks</th></tr>
        @foreach ($teachers as $t)
            <tr><td><a href="{{ route('admin.teachers.show', $t) }}">{{ $t->name }}</a></td><td>{{ $t->students }}</td><td>{{ $t->classes_held }}</td><td>{{ $t->present_marks }}</td></tr>
        @endforeach
    </table>
</div>
@endsection
