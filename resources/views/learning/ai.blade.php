@extends('layouts.app')
@section('title', 'AI helper')
@section('content')
<style>
    .ai { display:flex; flex-direction:column; gap:10px; }
    .ai .m { max-width:85%; padding:10px 14px; border-radius:12px; background:var(--soft); border:1px solid var(--line); white-space:pre-wrap; }
    .ai .m.me { align-self:flex-end; background:#e6f4e0; } @media (prefers-color-scheme: dark) { .ai .m.me { background:#1f3a1a; } }
</style>
<div class="card">
    <h2>{{ $lesson ? 'Ask about: '.$lesson->title : 'Ask the AI helper' }}</h2>
    <p class="muted">The helper explains lessons, makes practice quizzes and helps you revise. It can make mistakes, so check with your teacher for anything important.</p>
    <div class="ai">
        @foreach ($messages as $m)
            <div @class(['m', 'me' => $m->role === 'user'])>{{ $m->text }}</div>
        @endforeach
        <span id="end"></span>
    </div>
    @if ($blocked)
        <p class="warn" style="margin-top:12px">{{ $blocked }}</p>
    @else
        <form method="post" action="{{ route('ai.store', array_filter(['lesson' => $lesson?->id])) }}" style="margin-top:12px" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Thinking…';">@csrf
            <textarea name="question" rows="3" required maxlength="2000" style="width:100%" placeholder="Ask a question">{{ old('question') }}</textarea>
            <div class="row" style="margin-top:6px">
                <button>Ask</button>
                <button type="button" class="warn-btn" onclick="const f = this.form; f.question.value = 'Make me a practice quiz on this.'; f.requestSubmit();">Practice quiz</button>
                <button type="button" class="warn-btn" onclick="const f = this.form; f.question.value = 'What should I revise, based on my weaker marks? Give me a short plan.'; f.requestSubmit();">Help me revise</button>
            </div>
        </form>
    @endif
</div>
@endsection
