@extends('layouts.app')
@section('title', 'Feedback')
@section('content')
@php $office = ! auth()->user()->isStudent() && ! auth()->user()->isTeacher(); @endphp
@if ($student)
<div class="card">
    <h2>Tell us about your classes</h2>
    @if ($enrolments->isEmpty())<p class="muted">You have no teacher yet.</p>@else
    <form method="post" action="{{ route('feedback.store') }}">@csrf
        <div class="row">
            <div><label>Course and teacher</label><select name="enrolment_id">@foreach ($enrolments as $e)<option value="{{ $e->id }}">{{ $e->course->name }} · {{ $e->teacher->name }}</option>@endforeach</select></div>
            <div><label>How good are the classes?</label><select name="rating">@foreach ([5 => 'Excellent', 4 => 'Very good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'] as $n => $l)<option value="{{ $n }}">{{ $n }} · {{ $l }}</option>@endforeach</select></div>
        </div>
        <p><label>Anything to say</label><textarea name="comment" rows="3" style="width:100%"></textarea></p>
        <button>Send</button>
    </form>
    @endif
</div>
@endif
<div class="card">
    <h2>{{ $student ? 'What you sent' : 'What students say' }}</h2>
    <table>
        @forelse ($feedback as $f)
            <tr><td>{{ $f->created_at->timezone(config('school.timezone'))->format('j M Y') }}</td>
                @if ($office)<td>{{ $f->student->name }}</td>@endif
                <td>{{ $f->course?->name }}@unless (auth()->user()->isTeacher()) · {{ $f->teacher?->name }}@endunless</td>
                <td><b>{{ $f->rating }}/5</b> {{ $f->comment }}</td></tr>
        @empty
            <tr><td class="muted">No feedback yet.</td></tr>
        @endforelse
    </table>
    {{ $feedback->links() }}
</div>
@endsection
