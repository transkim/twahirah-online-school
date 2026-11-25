@extends('layouts.app')
@section('title', 'Attendance')
@section('content')
<h1>Attendance register</h1>
<form class="row" style="margin-bottom:16px"><input type="date" name="date" value="{{ $date }}"> <button>Show</button></form>
<p class="muted">Students are marked present automatically when they sign in (chat classes) or join their Zoom class. Correct any mark here.</p>
<div class="card">
    <table>
        <tr><th>Student</th><th>Course</th><th>Teacher</th><th>Mark</th><th>Change</th></tr>
        @forelse ($enrolments as $e)
            @php $m = $marks->get($e->id); @endphp
            <tr>
                <td>{{ $e->student->name }}</td>
                <td>{{ $e->course->name }} <span class="muted">({{ $e->course->mode }})</span></td>
                <td>{{ $e->teacher?->name }}</td>
                <td>@if ($m)<span @class(['pill', 'ok' => $m->status === 'present'])>{{ $m->status }}</span> <span class="muted">{{ ['login' => 'signed in', 'zoom' => 'Zoom', 'manual' => 'by hand'][$m->source] ?? '' }} {{ $m->marked_at->timezone(config('school.timezone'))->format('H:i') }}</span>@else<span class="muted">not marked</span>@endif</td>
                <td>
                    <form class="inline" method="post" action="{{ route('admin.attendance.update', $e) }}">@csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <button name="status" value="{{ $m?->status === 'present' ? 'absent' : 'present' }}">{{ $m?->status === 'present' ? 'Mark absent' : 'Mark present' }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No active students.</td></tr>
        @endforelse
    </table>
</div>
@endsection
