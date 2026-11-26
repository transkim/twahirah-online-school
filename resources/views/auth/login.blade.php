@extends('layouts.auth')
@section('title', 'Sign in')
@section('heading', 'Welcome back')
@section('subheading', 'Sign in to your account to continue')
@section('content')
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <div class="group">
                <label class="top" for="login">Username or phone</label>
                <div class="wrap">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.4 0-9 2.2-9 5.5V22h18v-2.5C21 16.2 16.4 14 12 14Z"/></svg>
                    <input id="login" name="login" value="{{ old('login') }}" placeholder="Phone, student number or username" autocomplete="username" required autofocus>
                </div>
            </div>
            <div class="group">
                <label class="top" for="password">Password</label>
                <div class="wrap">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 9V7A5 5 0 0 0 7 7v2H5v13h14V9h-2ZM9 7a3 3 0 1 1 6 0v2H9V7Z"/></svg>
                    <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                    <button type="button" class="eye" aria-label="Show password" aria-pressed="false" onclick="const p=document.getElementById('password');const show=p.type==='password';p.type=show?'text':'password';this.setAttribute('aria-pressed',show);this.setAttribute('aria-label',show?'Hide password':'Show password');">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5C6.5 5 2.7 9.2 1.5 12c1.2 2.8 5 7 10.5 7s9.3-4.2 10.5-7C21.3 9.2 17.5 5 12 5Zm0 11.5a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Zm0-2.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                    </button>
                </div>
            </div>
            <div class="row">
                <label><input type="checkbox" name="remember" value="1"> Remember me</label>
                <a class="help" href="{{ route('password.code') }}">First time or forgot password?</a>
            </div>
            <button class="go">Sign In</button>
        </form>
@endsection
