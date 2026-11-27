@extends('layouts.app')
@section('title', 'Lessons')
@section('content')
@php $kinds = ['notes' => 'Notes', 'audio' => 'Audio', 'video' => 'Video', 'recording' => 'Class recording']; @endphp
@if ($locked)
    <div class="card"><p class="warn">Lessons open while you have an active subscription.</p><p><a class="btn" href="{{ route('my.subscribe') }}">Subscribe</a></p></div>
@endif
<div class="card">
    <h2>Lesson library</h2>
    <table>
        <tr><th>Lesson</th><th>Course</th><th>Type</th><th>Added</th></tr>
        @forelse ($lessons as $l)
            <tr><td><a href="{{ route('lessons.show', $l) }}"><b>{{ $l->title }}</b></a></td>
                <td>{{ $l->course->name }}{{ $l->level ? ' · '.$l->level->name : '' }}</td>
                <td class="muted">{{ $kinds[$l->kind] ?? $l->kind }}</td>
                <td class="muted">{{ $l->created_at->timezone(auth()->user()->timezone)->format('j M Y') }} · {{ $l->teacher?->name }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">No lessons yet.</td></tr>
        @endforelse
    </table>
    {{ $lessons->links() }}
</div>
@if ($courses->isNotEmpty())
    <div class="card">
        <h2>Add a lesson</h2>
        <form method="post" action="{{ route('lessons.store') }}" enctype="multipart/form-data">@csrf
            <div class="row">
                @include('learning._course-picker')
                <div><label>Type</label><select name="kind">@foreach ($kinds as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            </div>
            <p><label>Title</label><input name="title" required maxlength="191" style="width:100%"></p>
            <p><label>Notes</label><textarea name="body" rows="5" style="width:100%"></textarea></p>
            <div class="row">
                <div><label>File (notes, audio or video, up to 100 MB)</label><input type="file" name="file"></div>
                <div><label>Or a link (Zoom recording, YouTube)</label><input type="url" name="link_url" placeholder="https://"></div>
            </div>
            <p style="margin-top:12px"><button>Add lesson</button></p>
        </form>
    </div>
@endif
@endsection
