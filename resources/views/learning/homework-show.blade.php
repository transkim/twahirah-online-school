@extends('layouts.app')
@section('title', $homework->title)
@section('content')
@php $tz = auth()->user()->timezone; @endphp
<div class="card">
    <h2>{{ $homework->title }}</h2>
    <p class="muted">{{ $homework->course->name }}{{ $homework->level ? ' · '.$homework->level->name : '' }} · {{ $homework->teacher?->name }}
        · {{ $homework->due_at ? 'due '.$homework->due_at->timezone($tz)->format('l j M, H:i') : 'no due date' }}</p>
    @if ($homework->instructions)<div>{!! nl2br(e($homework->instructions)) !!}</div>@endif
</div>
@if (auth()->user()->isStudent())
    <div class="card">
        <h2>Your answer</h2>
        @if ($mine)
            <p>Handed in {{ $mine->submitted_at->timezone($tz)->format('j M, H:i') }}.
                @if ($mine->attachment_path)<a href="{{ route('homework.file', $mine) }}">📎 {{ $mine->attachment_name }}</a>@endif</p>
            @if ($mine->body)<p>{!! nl2br(e($mine->body)) !!}</p>@endif
            @if ($mine->mark !== null)<p><b>Mark: {{ $mine->mark }}/100</b></p>@if ($mine->feedback)<p class="muted">{{ $mine->feedback }}</p>@endif @endif
        @endif
        @if ($mine?->mark === null)
            <form method="post" action="{{ route('homework.submit', $homework) }}" enctype="multipart/form-data">@csrf
                <p><textarea name="body" rows="4" style="width:100%" placeholder="Write your answer">{{ $mine?->body }}</textarea></p>
                <p><label>Voice recording or file</label><input type="file" name="file" accept="audio/*,image/*,.pdf,.doc,.docx"></p>
                <button>{{ $mine ? 'Hand in again' : 'Hand in' }}</button>
            </form>
        @endif
    </div>
@else
    <div class="card">
        <h2>Handed in ({{ $submissions->count() }})</h2>
        <table>
            @forelse ($submissions as $s)
                <tr><td><b>{{ $s->student->name }}</b><br><span class="muted">{{ $s->submitted_at->timezone($tz)->format('j M, H:i') }}</span></td>
                    <td>@if ($s->body){!! nl2br(e(\Illuminate\Support\Str::limit($s->body, 400))) !!}<br>@endif
                        @if ($s->attachment_path)
                            @if (str_starts_with((string) $s->attachment_mime, 'audio/'))<audio controls preload="none" src="{{ route('homework.file', $s) }}"></audio>@else<a href="{{ route('homework.file', $s) }}">📎 {{ $s->attachment_name }}</a>@endif
                        @endif</td>
                    <td>@if ($canMark)
                        <form class="row" method="post" action="{{ route('homework.mark', $s) }}">@csrf
                            <input type="number" name="mark" min="0" max="100" value="{{ $s->mark }}" placeholder="/100" style="width:80px" required>
                            <input name="feedback" value="{{ $s->feedback }}" placeholder="Comment">
                            <button>Save</button></form>
                        @elseif ($s->mark !== null) {{ $s->mark }}/100 @endif</td></tr>
            @empty
                <tr><td class="muted">Nobody has handed in yet.</td></tr>
            @endforelse
        </table>
    </div>
@endif
@endsection
