@php $tz = $me->timezone; @endphp
@forelse ($messages as $m)
    <div @class(['msg', 'mine' => $m->user_id === $me->id]) id="m{{ $m->id }}">
        <div class="who-line"><b>{{ $m->user->name }}</b> <span class="muted">{{ $m->created_at->timezone($tz)->format('j M, H:i') }}</span>
            @if ($m->forwarded_from_id)<span class="muted">· forwarded</span>@endif</div>
        @if ($m->isRemoved())
            <div class="muted"><i>This message was removed.</i></div>
        @else
            @if ($m->replyTo)<div class="quote">{{ $m->replyTo->user->name }}: {{ \Illuminate\Support\Str::limit($m->replyTo->isRemoved() ? 'removed' : ($m->replyTo->body ?? $m->replyTo->attachment_name), 80) }}</div>@endif
            @if ($m->attachment_path)
                @php $url = route('chat.file', $m); @endphp
                @if ($m->type === 'voice')<audio controls preload="none" src="{{ $url }}"></audio>
                @elseif ($m->type === 'image')<a href="{{ $url }}" target="_blank"><img src="{{ $url }}" alt="{{ $m->attachment_name }}" loading="lazy"></a>
                @elseif ($m->type === 'video')<video controls preload="none" src="{{ $url }}"></video>
                @else<a href="{{ $url }}">📎 {{ $m->attachment_name }}</a>@endif
            @endif
            @if ($m->body)<div class="text">{!! nl2br(e($m->body)) !!}</div>@endif
            <div class="tools">
                <button type="button" class="plain link" onclick="replyTo({{ $m->id }}, @js($m->user->name))">Reply</button>
                @if ($forwardTo->isNotEmpty())
                    <form class="inline" method="post" action="{{ route('chat.forward', $m) }}">@csrf
                        <select name="to" onchange="this.form.submit()"><option value="">Forward to…</option>@foreach ($forwardTo as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach</select></form>
                @endif
                @if ($canModerate)
                    <form class="inline" method="post" action="{{ route('chat.destroy', $m) }}">@csrf @method('delete')<button class="plain">Remove</button></form>
                @endif
                @if ($m->user_id === $me->id)
                    @php $seen = $members->filter(fn ($p) => $p->id !== $me->id && $p->pivot->last_read_at && \Illuminate\Support\Carbon::parse($p->pivot->last_read_at)->gte($m->created_at))->count(); @endphp
                    <span class="ticks" title="Seen by {{ $seen }}">{{ $seen > 0 ? '✓✓' : '✓' }}</span>
                @endif
            </div>
        @endif
    </div>
@empty
    <p class="muted">No messages yet. Say assalamu alaikum.</p>
@endforelse
<span id="end"></span>
