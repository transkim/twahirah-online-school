@extends('layouts.app')
@section('title', 'Student Insights')
@section('content')
<div class="grid">
    <div class="card stat orange"><span>Not seen for a week</span><b>{{ $quiet->count() }}</b><small>studying, but no attendance in 7 days</small></div>
    <div class="card stat red"><span>Subscription lapsed</span><b>{{ $lapsed }}</b><small>had a plan, none active now</small></div>
    <div class="card stat slate"><span>Plans ending this week</span><b>{{ $endingSoon->count() }}</b></div>
</div>
<div class="split">
    <div class="card">
        <h2>Gone quiet</h2>
        <table>
            @forelse ($quiet->take(200) as $e)
                <tr><td><a href="{{ route('admin.students.show', $e->student) }}">{{ $e->student->name }}</a><br><span class="muted">{{ $e->student->phone }}</span></td>
                    <td>{{ $e->course->name }}<br><span class="muted">{{ $e->teacher?->name }}</span></td><td class="muted">{{ $e->last_present ? 'last present '.$e->last_present : 'never present' }}</td></tr>
            @empty
                <tr><td class="muted">Everyone has been in class this week.</td></tr>
            @endforelse
        </table>
    </div>
    <div class="card">
        <h2>Plans ending this week</h2>
        <table>
            @forelse ($endingSoon as $s)
                <tr><td>{{ $s->student->name }}</td><td class="muted">{{ $s->plan->name }}</td><td>{{ $s->ends_at->timezone(config('school.timezone'))->format('D j M') }}</td></tr>
            @empty
                <tr><td class="muted">None.</td></tr>
            @endforelse
        </table>
    </div>
</div>
@endsection
