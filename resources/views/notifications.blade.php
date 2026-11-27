@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="card">
    <h2>Notifications</h2>
    <table>
        @forelse ($notifications as $n)
            <tr><td><a href="{{ route('notifications.open', $n->id) }}"><b @class(['muted' => $n->read_at])>{{ $n->data['title'] ?? '' }}</b></a><br><span class="muted">{{ \Illuminate\Support\Str::limit($n->data['body'] ?? '', 140) }}</span></td>
                <td class="muted" style="white-space:nowrap">{{ $n->created_at->timezone(auth()->user()->timezone)->diffForHumans() }}</td></tr>
        @empty
            <tr><td class="muted">Nothing new.</td></tr>
        @endforelse
    </table>
    {{ $notifications->links() }}
</div>
@endsection
