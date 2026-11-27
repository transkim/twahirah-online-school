@extends('layouts.app')
@section('title', 'Events')
@section('content')
@php $tz = config('school.timezone'); @endphp
<div class="card">
    <h2>Add an event</h2>
    <form method="post" action="{{ route('admin.events.store') }}">@csrf
        <div class="row">
            <div><label>Title</label><input name="title" required maxlength="191"></div>
            <div><label>Starts (Kampala time)</label><input type="datetime-local" name="starts_at" required></div>
            <div><label>Ends</label><input type="datetime-local" name="ends_at"></div>
            <div><label>Place or link</label><input name="place"></div>
        </div>
        <p><label>About</label><textarea name="description" rows="2" style="width:100%"></textarea></p>
        <div class="row"><label><input type="checkbox" name="announce" value="1"> Tell all students and teachers</label><button>Save event</button></div>
    </form>
</div>
<div class="card">
    <h2>Coming up</h2>
    <table>
        @forelse ($upcoming as $e)
            <tr><td><b>{{ $e->title }}</b>@if ($e->description)<br><span class="muted">{{ $e->description }}</span>@endif</td>
                <td>{{ $e->starts_at->timezone($tz)->format('D j M Y, H:i') }}</td><td>{{ $e->place }}</td>
                <td><form class="inline" method="post" action="{{ route('admin.events.destroy', $e) }}">@csrf @method('delete')<button class="plain">Remove</button></form></td></tr>
        @empty
            <tr><td class="muted">No events coming up.</td></tr>
        @endforelse
    </table>
</div>
@if ($past->isNotEmpty())
<div class="card"><h2>Past events</h2><table>@foreach ($past as $e)<tr><td>{{ $e->title }}</td><td class="muted">{{ $e->starts_at->timezone($tz)->format('j M Y') }}</td></tr>@endforeach</table></div>
@endif
@endsection
