@extends('layouts.app')
@section('title', $attempt->exam->title)
@section('content')
<div class="card" style="position:sticky;top:70px;z-index:5">
    <b>{{ $attempt->exam->title }}</b> · Time left: <b id="timer" class="warn"></b>
    <span class="muted">· answers are handed in by themselves when the time ends</span>
</div>
<form method="post" action="{{ route('exams.submit', $attempt) }}" enctype="multipart/form-data" id="exam">@csrf
    @foreach ($questions as $i => $q)
        <div class="card">
            <p><b>{{ $i + 1 }}.</b> {!! nl2br(e($q->prompt)) !!} <span class="muted">({{ $q->points }} pt)</span></p>
            @if ($q->type === 'choice')
                @foreach ($q->options as $n => $o)
                    <label style="text-transform:none;letter-spacing:0;font-size:15px;color:var(--ink);font-weight:500;margin:6px 0"><input type="radio" name="answers[{{ $q->id }}][choice]" value="{{ $n }}"> {{ $o }}</label>
                @endforeach
            @else
                <textarea name="answers[{{ $q->id }}][text]" rows="4" style="width:100%" placeholder="Your answer"></textarea>
                <p><label>Or a voice recording</label><input type="file" name="files[{{ $q->id }}]" accept="audio/*"></p>
            @endif
        </div>
    @endforeach
    <button>Hand in</button>
</form>
<script>
    const due = new Date(@js($attempt->due_at->toIso8601String())).getTime();
    const el = document.getElementById('timer');
    let sent = false;
    const tick = () => {
        const left = Math.max(0, Math.floor((due - Date.now()) / 1000));
        el.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
        if (left === 0 && !sent) { sent = true; document.getElementById('exam').submit(); }
    };
    tick(); setInterval(tick, 1000);
</script>
@endsection
