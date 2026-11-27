@extends('layouts.app')
@section('title', 'Homework')
@section('content')
@php $me = auth()->user(); $tz = $me->timezone; @endphp
<div class="card">
    <h2>Homework</h2>
    <table>
        <tr><th>Homework</th><th>Course</th><th>Due</th><th>{{ $me->isStudent() ? 'Yours' : 'Handed in' }}</th></tr>
        @forelse ($homework as $h)
            @php $mine = $me->isStudent() ? $h->submissions->first() : null; @endphp
            <tr><td><a href="{{ route('homework.show', $h) }}"><b>{{ $h->title }}</b></a></td>
                <td>{{ $h->course->name }}{{ $h->level ? ' · '.$h->level->name : '' }}</td>
                <td @class(['bad' => $h->due_at && $h->due_at->isPast() && ! $mine])>{{ $h->due_at?->timezone($tz)->format('j M, H:i') ?? 'No date' }}</td>
                <td>@if ($me->isStudent())
                        @if ($mine?->mark !== null)<span class="pill ok">{{ $mine->mark }}/100</span>@elseif ($mine)<span class="pill">handed in</span>@else<span class="muted">not yet</span>@endif
                    @else {{ $h->submissions_count }} @endif</td></tr>
        @empty
            <tr><td colspan="4" class="muted">No homework yet.</td></tr>
        @endforelse
    </table>
    {{ $homework->links() }}
</div>
@if ($courses->isNotEmpty())
    <div class="card">
        <h2>Set homework</h2>
        <form method="post" action="{{ route('homework.store') }}">@csrf
            <div class="row">
                @include('learning._course-picker')
                <div><label>Due</label><input type="datetime-local" name="due_at"></div>
            </div>
            <p><label>Title</label><input name="title" required maxlength="191" style="width:100%"></p>
            <p><label>Instructions</label><textarea name="instructions" rows="4" style="width:100%" placeholder="For example: recite Surah Al-Fatiha and send a voice recording"></textarea></p>
            <button>Set homework</button>
        </form>
    </div>
@endif
@endsection
