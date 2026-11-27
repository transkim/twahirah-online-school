@extends('layouts.app')
@section('title', 'Announcements')
@section('content')
@if ($staff)
    <div class="card">
        <h2>New announcement</h2>
        <form method="post" action="{{ route('announcements.store') }}">@csrf
            <p><label>Title</label><input name="title" required maxlength="191" style="width:100%"></p>
            <p><label>Message</label><textarea name="body" rows="4" required style="width:100%"></textarea></p>
            <div class="row">
                <div><label>Send to</label><select name="audience"><option value="everyone">Everyone</option><option value="students">All students</option><option value="teachers">All teachers</option><option value="course">One course</option></select></div>
                <div><label>Course (if one course)</label><select name="course_id"><option value="">—</option>@foreach ($courses as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->mode }})</option>@endforeach</select></div>
                <div><label><input type="checkbox" name="sms" value="1"> Also send by SMS</label></div>
                <button>Send</button>
            </div>
        </form>
    </div>
@endif
<div class="card">
    <h2>Announcements</h2>
    @forelse ($announcements as $a)
        <div style="padding:10px 0;border-bottom:1px solid var(--line)">
            <b>{{ $a->title }}</b> <span class="muted">· {{ $a->created_at->timezone(auth()->user()->timezone)->format('j M Y, H:i') }}
                @if ($staff) · to {{ $a->audience === 'course' ? $a->course?->name : $a->audience }} ({{ $a->recipients }}){{ $a->sent_by_sms ? ' · SMS' : '' }}@endif</span>
            <div>{!! nl2br(e($a->body)) !!}</div>
        </div>
    @empty
        <p class="muted">No announcements yet.</p>
    @endforelse
    {{ $announcements->links() }}
</div>
@endsection
