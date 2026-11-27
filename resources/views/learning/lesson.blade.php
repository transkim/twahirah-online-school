@extends('layouts.app')
@section('title', $lesson->title)
@section('content')
<div class="card">
    <h2>{{ $lesson->title }}</h2>
    <p class="muted">{{ $lesson->course->name }}{{ $lesson->level ? ' · '.$lesson->level->name : '' }} · {{ $lesson->teacher?->name }} · {{ $lesson->created_at->timezone(auth()->user()->timezone)->format('j M Y') }}</p>
    @if ($lesson->attachment_path)
        @php $url = route('lessons.file', $lesson); $mime = (string) $lesson->attachment_mime; @endphp
        @if (str_starts_with($mime, 'audio/'))<audio controls src="{{ $url }}" style="width:100%"></audio>
        @elseif (str_starts_with($mime, 'video/'))<video controls src="{{ $url }}" style="max-width:100%"></video>
        @elseif (str_starts_with($mime, 'image/'))<img src="{{ $url }}" alt="" style="max-width:100%">
        @endif
        <p><a href="{{ $url }}">📎 {{ $lesson->attachment_name }}</a></p>
    @endif
    @if ($lesson->link_url)<p><a class="btn light" href="{{ $lesson->link_url }}" target="_blank" rel="noopener">Open the recording or video</a></p>@endif
    @if ($lesson->body)<div style="margin-top:10px">{!! nl2br(e($lesson->body)) !!}</div>@endif
    @if (auth()->user()->isStudent() && config('school.ai.enabled'))
        <p style="margin-top:14px"><a class="btn" href="{{ route('ai.index', ['lesson' => $lesson->id]) }}">Ask the AI helper about this lesson</a></p>
    @endif
    @if (auth()->user()->isAdmin() || $lesson->teacher_id === auth()->id())
        <form method="post" action="{{ route('lessons.destroy', $lesson) }}" style="margin-top:14px">@csrf @method('delete')<button class="plain">Remove lesson</button></form>
    @endif
</div>
@endsection
