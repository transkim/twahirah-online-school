@extends('layouts.app')
@section('title', 'Self-registered')
@section('content')
<div class="card">
    <h2>Signed up in the app, waiting to be checked ({{ $students->count() }})</h2>
    <p class="muted">They can already subscribe and learn. Approving gives them the next free student number. Check first that they are not already a student under another phone.</p>
    <table>
        @forelse ($students as $s)
            <tr><td><a href="{{ route('admin.students.show', $s) }}"><b>{{ $s->name }}</b></a><br><span class="muted">{{ $s->phone }} · {{ $s->country }} · {{ $s->created_at->diffForHumans() }}</span></td>
                <td>{{ $s->enrolments->map(fn ($e) => $e->course->name.' ('.$e->course->mode.')')->join(', ') }}</td>
                <td><form class="inline" method="post" action="{{ route('admin.self-registered.decide', $s) }}">@csrf<button name="action" value="approve">Approve</button> <button class="plain" name="action" value="reject">Reject</button></form></td></tr>
        @empty
            <tr><td class="muted">Nobody waiting.</td></tr>
        @endforelse
    </table>
    <p class="muted">Sign-up page for new students: {{ route('register') }}</p>
</div>
@endsection
