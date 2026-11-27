@extends('layouts.auth')
@section('title', 'Sign up')
@section('heading', 'Join Twahirah Online School')
@section('subheading', 'Make your account, choose a course, then pick a plan.')
@section('content')
        <form method="post" action="{{ route('register.store') }}">
            @csrf
            <div class="group"><label class="top" for="name">Full name</label><div class="wrap"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.4 0-9 2.2-9 5.5V22h18v-2.5C21 16.2 16.4 14 12 14Z"/></svg><input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></div></div>
            <div class="group"><label class="top" for="phone">Phone number</label><div class="wrap"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.4 0-9 2.2-9 5.5V22h18v-2.5C21 16.2 16.4 14 12 14Z"/></svg><input id="phone" name="phone" value="{{ old('phone') }}" required placeholder="0700 000 000" inputmode="tel" autocomplete="tel"></div></div>
            <div class="group"><label class="top" for="gender">Gender</label><div class="wrap"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.4 0-9 2.2-9 5.5V22h18v-2.5C21 16.2 16.4 14 12 14Z"/></svg><select id="gender" name="gender"><option value="">Choose…</option><option value="male">Male</option><option value="female">Female</option></select></div></div>
            <div class="group"><label class="top" for="country">Country</label><div class="wrap"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.4 0-9 2.2-9 5.5V22h18v-2.5C21 16.2 16.4 14 12 14Z"/></svg><input id="country" name="country" value="{{ old('country', 'Uganda') }}" maxlength="60"></div></div>
            <div class="group"><label class="top" for="course_id">Course</label><div class="wrap"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.4 0-9 2.2-9 5.5V22h18v-2.5C21 16.2 16.4 14 12 14Z"/></svg><select id="course_id" name="course_id" required>@foreach ($courses as $c)<option value="{{ $c->id }}" @selected(old('course_id') == $c->id)>{{ $c->name }} ({{ $c->mode === 'zoom' ? 'live on Zoom' : 'chat class' }})</option>@endforeach</select></div></div>
            <div class="group"><label class="top" for="password">Password</label><div class="wrap"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 9V7A5 5 0 0 0 7 7v2H5v13h14V9h-2ZM9 7a3 3 0 1 1 6 0v2H9V7Z"/></svg><input id="password" type="password" name="password" required minlength="8" placeholder="At least 8 characters" autocomplete="new-password"></div></div>
            <div class="group"><label class="top" for="password_confirmation">Type it again</label><div class="wrap"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 9V7A5 5 0 0 0 7 7v2H5v13h14V9h-2ZM9 7a3 3 0 1 1 6 0v2H9V7Z"/></svg><input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div></div>
            <button class="go">Sign up</button>
        </form>
        <a class="back" href="{{ route('login') }}">Already a student? Sign in</a>
@endsection
