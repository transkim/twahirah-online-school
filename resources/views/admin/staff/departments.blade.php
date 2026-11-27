@extends('layouts.app')
@section('title', 'Departments')
@section('content')
<div class="card">
    <h2>Departments</h2>
    <table>
        @forelse ($departments as $d)
            <tr><td><form class="row" method="post" action="{{ route('admin.staff.departments.update', $d) }}">@csrf @method('put')
                <input name="name" value="{{ $d->name }}" required>
                <select name="head_id"><option value="">No head</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($d->head_id === $s->id)>{{ $s->name }}</option>@endforeach</select>
                <span class="muted">{{ $d->members_count }} people</span><button>Save</button></form></td></tr>
        @empty
            <tr><td class="muted">No departments yet.</td></tr>
        @endforelse
    </table>
    <form class="row" method="post" action="{{ route('admin.staff.departments.store') }}" style="margin-top:12px">@csrf
        <input name="name" placeholder="For example: Quran department" required>
        <select name="head_id"><option value="">Head of department</option>@foreach ($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
        <button>Add department</button>
    </form>
</div>
@endsection
