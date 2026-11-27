@extends('layouts.app')
@section('title', 'Chat')
@section('content')
<div class="card">
    <h2>Groups and chats</h2>
    <form class="row" method="get" style="margin-bottom:12px">
        <input name="q" value="{{ $search }}" placeholder="Find a group">
        <button>Find</button>
    </form>
    <table>
        @forelse ($groups as $g)
            <tr>
                <td><a href="{{ route('chat.show', $g) }}"><b>{{ $g->name }}</b></a>
                    <span class="muted">· {{ ['class' => 'class group', 'direct' => 'one to one', 'custom' => 'group'][$g->kind] ?? $g->kind }}</span></td>
                <td class="muted">{{ $g->members_count }} people</td>
                <td class="muted">{{ $g->last_message_at ? \Illuminate\Support\Carbon::parse($g->last_message_at)->timezone(auth()->user()->timezone)->diffForHumans() : 'no messages yet' }}</td>
            </tr>
        @empty
            <tr><td class="muted">No groups yet. You join your class group when you start a course.</td></tr>
        @endforelse
    </table>
    {{ $groups->links() }}
</div>
@if ($canCreate)
    <div class="card">
        <h2>Make a group</h2>
        <form class="row" method="post" action="{{ route('chat.create') }}">@csrf
            <input name="name" placeholder="Group name" required maxlength="120">
            <button>Make group</button>
        </form>
    </div>
@endif
@endsection
