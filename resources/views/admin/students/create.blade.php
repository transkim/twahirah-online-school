@extends('layouts.app')
@section('title', 'Add a student')
@section('content')
<h1>Add a student</h1>
<form class="card" method="post" action="{{ route('admin.students.store') }}" style="max-width:520px">
    @csrf
    <p><label>Full name</label><input name="name" value="{{ old('name') }}" required style="width:100%"></p>
    <p><label>Phone number (used to sign in)</label><input name="phone" value="{{ old('phone') }}" required></p>
    <p><label>Email (optional)</label><input name="email" type="email" value="{{ old('email') }}"></p>
    <p><label>Username (optional)</label><input name="username" value="{{ old('username') }}"></p>
    <p><label>Student number (optional)</label><input name="student_number" value="{{ old('student_number') }}"></p>
    <p><label>Country</label><input name="country" value="{{ old('country', 'Uganda') }}"></p>
    <p><label>Time zone</label><input name="timezone" value="{{ old('timezone', config('school.timezone')) }}"></p>
    <p><label>Starting password (at least 8 characters)</label><input name="password" type="text" required minlength="8"></p>
    <button>Add student</button>
</form>
@endsection
