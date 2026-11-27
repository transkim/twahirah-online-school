@extends('layouts.app')
@section('title', $group->name)
@section('content')
<style>
    .chatbox { display:flex; flex-direction:column; gap:10px; max-height:62vh; overflow-y:auto; padding:4px; }
    .msg { max-width:78%; background:var(--soft); border:1px solid var(--line); border-radius:12px; padding:8px 12px; }
    .msg.mine { align-self:flex-end; background:#e6f4e0; } @media (prefers-color-scheme: dark) { .msg.mine { background:#1f3a1a; } }
    .msg img, .msg video { max-width:260px; max-height:260px; border-radius:8px; display:block; margin-top:4px; }
    .msg audio { max-width:100%; margin-top:4px; } .msg .who-line { font-size:12.5px; }
    .msg .quote { border-left:3px solid var(--brand); padding-left:8px; font-size:12.5px; color:var(--muted); margin:4px 0; }
    .msg .tools { display:flex; gap:6px; align-items:center; margin-top:4px; font-size:12px; } .msg .tools select { min-height:0; padding:2px 4px; font-size:12px; }
    .msg .ticks { margin-left:auto; color:#2d8c1e; font-weight:700; } button.link { color:var(--brand); }
    @media (max-width: 700px) { .msg { max-width:94%; } }
</style>
@if ($blocked)
    <div class="card"><p class="warn">{{ $blocked }}</p><p><a class="btn" href="{{ route('my.subscribe') }}">Renew subscription</a></p></div>
@else
    <div class="card">
        <h2>{{ $group->name }}</h2>
        <div class="chatbox" id="chatbox">@include('chat.messages')</div>
        <form method="post" action="{{ route('chat.store', $group) }}" enctype="multipart/form-data" id="composer" style="margin-top:12px">@csrf
            <input type="hidden" name="reply_to_id" id="reply_to_id">
            <input type="hidden" name="duration_seconds" id="duration_seconds">
            <div id="replying" class="muted" style="display:none;margin-bottom:6px"></div>
            <textarea name="body" rows="2" style="width:100%" placeholder="Write a message">{{ old('body') }}</textarea>
            <div class="row" style="margin-top:6px">
                <input type="file" name="file" id="file" accept="audio/*,image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                <button type="button" class="warn-btn" id="record">🎤 Record voice</button>
                <button>Send</button>
            </div>
            <p class="muted" id="recording" style="display:none">Recording… tap Stop when done.</p>
        </form>
    </div>
@endif
<div class="card">
    <h2>People ({{ $members->count() }})</h2>
    <table>
        @foreach ($members as $p)
            <tr><td>{{ $p->name }} <span class="muted">{{ $p->pivot->role }}</span> @if ($p->pivot->muted)<span class="pill">muted</span>@endif</td>
                <td>@if ($canModerate && $p->id !== $me->id)
                    <form class="inline" method="post" action="{{ route('chat.members.update', [$group, $p]) }}">@csrf
                        <button class="plain" name="action" value="{{ $p->pivot->muted ? 'unmute' : 'mute' }}">{{ $p->pivot->muted ? 'Unmute' : 'Mute' }}</button>
                        <button class="plain" name="action" value="remove">Remove</button></form>
                @endif</td></tr>
        @endforeach
    </table>
    @if ($canModerate || $group->created_by === $me->id)
        <form class="row" method="post" action="{{ route('chat.members.store', $group) }}" style="margin-top:12px">@csrf
            <input name="login" placeholder="Student number, phone or username" required>
            <button>Add person</button>
        </form>
    @endif
</div>
@unless ($blocked)
<script>
    const box = document.getElementById('chatbox');
    box.scrollTop = box.scrollHeight;
    function replyTo(id, name) {
        document.getElementById('reply_to_id').value = id;
        const r = document.getElementById('replying'); r.style.display = 'block'; r.textContent = 'Replying to ' + name;
    }
    // New messages appear every few seconds without reloading the page.
    setInterval(async () => {
        if (document.hidden) return;
        const atBottom = box.scrollTop + box.clientHeight >= box.scrollHeight - 40;
        const res = await fetch(@js(route('chat.show', $group)) + '?partial=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (res.ok) { box.innerHTML = await res.text(); if (atBottom) box.scrollTop = box.scrollHeight; }
    }, 8000);
    // Voice notes recorded in the browser are sent like a file.
    const rec = document.getElementById('record');
    let recorder = null, chunks = [], started = 0;
    if (!navigator.mediaDevices || !window.MediaRecorder) { rec.style.display = 'none'; }
    rec.addEventListener('click', async () => {
        if (recorder) { recorder.stop(); return; }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            recorder = new MediaRecorder(stream); chunks = []; started = Date.now();
            recorder.ondataavailable = e => chunks.push(e.data);
            recorder.onstop = () => {
                stream.getTracks().forEach(t => t.stop());
                const type = recorder.mimeType || 'audio/webm';
                const file = new File(chunks, 'voice-note.' + (type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm'), { type });
                const dt = new DataTransfer(); dt.items.add(file);
                document.getElementById('file').files = dt.files;
                document.getElementById('duration_seconds').value = Math.round((Date.now() - started) / 1000);
                recorder = null; rec.textContent = '🎤 Record voice'; document.getElementById('recording').style.display = 'none';
                document.getElementById('composer').submit();
            };
            recorder.start(); rec.textContent = '■ Stop and send'; document.getElementById('recording').style.display = 'block';
        } catch (e) { alert('The microphone is not available here. You can attach a recording instead.'); }
    });
</script>
@endunless
@endsection
