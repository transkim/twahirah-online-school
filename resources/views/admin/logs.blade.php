@extends('layouts.app')
@section('title', 'System Logs')
@section('content')
<div class="card">
    <form class="row" method="get" style="margin-bottom:12px"><input name="q" value="{{ $search }}" placeholder="Person, page or record"><button>Find</button></form>
    <table>
        <tr><th>When</th><th>Who</th><th>What</th><th>Record</th></tr>
        @forelse ($logs as $l)
            <tr><td style="white-space:nowrap">{{ $l->created_at->timezone(config('school.timezone'))->format('j M Y, H:i') }}</td><td>{{ $l->user?->name }}</td>
                <td>{{ \Illuminate\Support\Str::of($l->action)->replace(['admin.', '.'], ['', ' '])->ucfirst() }} <span class="muted">{{ $l->method }}</span></td><td class="muted">{{ $l->subject }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">Nothing recorded yet.</td></tr>
        @endforelse
    </table>
    {{ $logs->links() }}
</div>
@endsection
