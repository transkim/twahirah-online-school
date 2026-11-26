@extends('layouts.auth')
@section('title', 'Enter your code')
@section('heading', 'Enter your code')
@section('subheading', 'Type the 6-digit code from the SMS, then choose a new password.')
@section('content')
        <form method="post" action="{{ route('password.code.update') }}">
            @csrf
            <div class="group">
                <label class="top" for="code">Code from SMS</label>
                <div class="wrap">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2Z"/></svg>
                    <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6 digits" autocomplete="one-time-code" required autofocus>
                </div>
            </div>
            <div class="group">
                <label class="top" for="password">New password</label>
                <div class="wrap">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 9V7A5 5 0 0 0 7 7v2H5v13h14V9h-2ZM9 7a3 3 0 1 1 6 0v2H9V7Z"/></svg>
                    <input id="password" type="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required>
                </div>
            </div>
            <div class="group">
                <label class="top" for="password_confirmation">Type it again</label>
                <div class="wrap">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 9V7A5 5 0 0 0 7 7v2H5v13h14V9h-2ZM9 7a3 3 0 1 1 6 0v2H9V7Z"/></svg>
                    <input id="password_confirmation" type="password" name="password_confirmation" placeholder="The same password" autocomplete="new-password" minlength="8" required>
                </div>
            </div>
            <button class="go">Save password and sign in</button>
        </form>
        <a class="back" href="{{ route('password.code') }}">Didn't get it? Ask for a new code</a>
@endsection
