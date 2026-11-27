@extends('layouts.app')
@section('title', 'My live classes')
@section('content')
@php $tz = $teacher->timezone; $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; @endphp
<div class="card">
    <h2>Booked classes</h2>
    <table>
        <tr><th>When ({{ $tz }})</th><th>Course</th><th>Type</th><th>Students</th><th></th></tr>
        @forelse ($sessions as $s)
            <tr>
                <td>{{ $s->starts_at->timezone($tz)->format('D j M, H:i') }}</td>
                <td>{{ $s->course->name }}</td>
                <td class="muted">{{ $s->kind === 'group' ? 'Small group' : 'One to one' }}</td>
                <td>@foreach ($s->bookings as $b){{ $b->student->name }}@if ($b->joined_at) <span class="pill ok">joined</span>@endif<br>@endforeach</td>
                <td>@if ($s->join_url)<a class="btn" href="{{ $s->join_url }}" target="_blank" rel="noopener">Open Zoom</a>@else<span class="muted">Zoom link not ready</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No classes booked yet. Students book in your free hours below.</td></tr>
        @endforelse
    </table>
</div>
<div class="card">
    <h2>My free hours</h2>
    <p class="muted">Kampala time. Students can book live classes with you in these hours.</p>
    <table>
        @forelse ($availabilities as $a)
            <tr><td>{{ $days[$a->weekday] }}</td><td>{{ substr($a->start_time, 0, 5) }}–{{ substr($a->end_time, 0, 5) }}</td>
                <td><form class="inline" method="post" action="{{ route('teach.availability.destroy', $a) }}">@csrf @method('delete')<button class="plain">Remove</button></form></td></tr>
        @empty
            <tr><td class="muted">No free hours yet.</td></tr>
        @endforelse
    </table>
    <form class="row" method="post" action="{{ route('teach.availability.store') }}" style="margin-top:12px">@csrf
        <select name="weekday">@foreach ($days as $i => $d)<option value="{{ $i }}">{{ $d }}</option>@endforeach</select>
        <input type="time" name="start_time" required> <input type="time" name="end_time" required>
        <button>Add</button>
    </form>
</div>
@endsection
