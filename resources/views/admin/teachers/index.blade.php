@extends('layouts.app')
@section('title', 'Teachers')
@section('content')
<h1>Teachers</h1>
<div class="card">
    <table>
        <tr><th>Teacher</th><th>Students</th><th>Teaches</th></tr>
        @forelse ($teachers as $t)
            <tr><td><a href="{{ route('admin.teachers.show', $t) }}">{{ $t->name }}</a> @unless ($t->active)<span class="pill">inactive</span>@endunless</td><td>{{ $t->students }}</td><td>{{ $t->teacherCourses->map(fn ($tc) => $tc->course->name.' ('.$tc->course->mode.')')->join(', ') ?: '—' }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No teachers yet.</td></tr>
        @endforelse
    </table>
</div>
<form class="card" method="post" action="{{ route('admin.teachers.store') }}" style="max-width:520px">@csrf
    <h2>Add a teacher</h2>
    <p><label>Full name</label><input name="name" value="{{ old('name') }}" required style="width:100%"></p>
    <p class="row"><span><label>Username</label><input name="username" value="{{ old('username') }}" required></span><span><label>Phone</label><input name="phone" value="{{ old('phone') }}"></span></p>
    <p><label>Email (optional)</label><input name="email" type="email" value="{{ old('email') }}"></p>
    <p><label>Starting password (at least 8 characters)</label><input name="password" type="text" required minlength="8"></p>
    <button>Add teacher</button>
</form>
@endsection
