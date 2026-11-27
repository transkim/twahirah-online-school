@extends('layouts.app')
@section('title', 'Work Plans')
@section('content')
@if (auth()->user()->isTeacher())
<div class="card">
    <h2>This week's plan (week of {{ \Illuminate\Support\Carbon::parse($week)->format('j M') }})</h2>
    <form method="post" action="{{ route('work-plans.store') }}">@csrf
        <input type="hidden" name="week_start" value="{{ $week }}">
        <p><label>What I plan to cover</label><textarea name="plan" rows="4" required style="width:100%">{{ $mine?->plan }}</textarea></p>
        <p><label>What was done (fill in at the end of the week)</label><textarea name="done" rows="3" style="width:100%">{{ $mine?->done }}</textarea></p>
        <button>Save</button>
    </form>
</div>
@endif
<div class="card">
    <h2>{{ $office ? 'Teachers\' work plans' : 'Past weeks' }}</h2>
    <table>
        @forelse ($plans as $p)
            <tr><td style="white-space:nowrap">{{ $p->week_start->format('j M Y') }}@if ($office)<br><b>{{ $p->teacher->name }}</b>@endif</td>
                <td><b>Plan:</b> {!! nl2br(e($p->plan)) !!}@if ($p->done)<br><b>Done:</b> {!! nl2br(e($p->done)) !!}@endif
                    @if ($p->office_comment)<br><span class="warn">Office: {{ $p->office_comment }}</span>@endif</td>
                <td>@if ($office)<form method="post" action="{{ route('admin.work-plans.comment', $p) }}">@csrf<input name="office_comment" value="{{ $p->office_comment }}" placeholder="Comment"> <button>Save</button></form>@endif</td></tr>
        @empty
            <tr><td class="muted">No work plans yet.</td></tr>
        @endforelse
    </table>
</div>
@endsection
