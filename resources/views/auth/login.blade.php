@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<div class="card" style="max-width:380px;margin:48px auto">
    <h1>Sign in</h1>
    <p class="muted">{{ config('school.name') }}</p>
    <form method="post" action="{{ route('login.store') }}">
        @csrf
        <p><label for="login">Username or phone number</label><input id="login" name="login" value="{{ old('login') }}" required autofocus style="width:100%"></p>
        <p><label for="password">Password</label><input id="password" type="password" name="password" required style="width:100%"></p>
        <p><label style="display:inline"><input type="checkbox" name="remember" value="1"> Keep me signed in</label></p>
        <button style="width:100%">Sign in</button>
    </form>
</div>
@endsection
