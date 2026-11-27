@extends('layouts.app')
@section('title', 'Change password')
@section('content')
<div class="card" style="max-width:460px">
    <h2>Change your password</h2>
    <form method="post" action="{{ route('account.password.update') }}">@csrf @method('put')
        <p><label for="current_password">Current password</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password" style="width:100%"></p>
        <p><label for="password">New password (8 or more characters)</label><input id="password" type="password" name="password" required autocomplete="new-password" style="width:100%"></p>
        <p><label for="password_confirmation">New password again</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" style="width:100%"></p>
        <button>Save new password</button>
    </form>
</div>
@endsection
