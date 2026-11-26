@extends('layouts.auth')
@section('title', 'Get a code')
@section('heading', 'Set your password')
@section('subheading', 'First time here, or forgot your password? We will send a code to your phone.')
@section('content')
        <form method="post" action="{{ route('password.code.send') }}">
            @csrf
            <div class="group">
                <label class="top" for="login">Phone or student number</label>
                <div class="wrap">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 1H7a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2Zm-5 21a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm5-5H7V4h10v13Z"/></svg>
                    <input id="login" name="login" value="{{ old('login') }}" placeholder="0752 084 475 or T/26/7530" autocomplete="username" required autofocus>
                </div>
                <p class="hint">Use the phone number the school has for you.</p>
            </div>
            <button class="go">Send me a code</button>
        </form>
        <a class="back" href="{{ route('login') }}">Back to sign in</a>
@endsection
