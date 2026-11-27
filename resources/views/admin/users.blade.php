@extends('layouts.app')
@section('title', 'System Users')
@section('content')
<div class="card">
    <h2>Add an account</h2>
    <form class="row" method="post" action="{{ route('admin.users.store') }}">@csrf
        <div><label>Name</label><input name="name" required value="{{ old('name') }}"></div>
        <div><label>Username</label><input name="username" required value="{{ old('username') }}"></div>
        <div><label>Phone</label><input name="phone" value="{{ old('phone') }}"></div>
        <div><label>Email</label><input type="email" name="email" value="{{ old('email') }}"></div>
        <div><label>Role</label><select name="role">@foreach ($roles as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div><label>Password</label><input type="password" name="password" required autocomplete="new-password"></div>
        <button>Add</button>
    </form>
    <p class="muted">Administrators can do everything. Finance can see students, payments and reports. Teachers see their own classes and students.</p>
</div>
<div class="card">
    <h2>Accounts</h2>
    <p>@foreach (['' => 'All'] + $roles as $k => $v)<a href="{{ route('admin.users.index', array_filter(['role' => $k])) }}" class="pill" style="margin-right:4px">{{ $v }}</a>@endforeach</p>
    <table>
        <tr><th>Name</th><th>Username</th><th>Role and access</th></tr>
        @foreach ($users as $u)
            <tr><td>{{ $u->name }}<br><span class="muted">{{ $u->phone }}</span></td><td>{{ $u->username }}</td>
                <td><form class="row" method="post" action="{{ route('admin.users.update', $u) }}">@csrf @method('put')
                    <select name="role">@foreach ($roles as $k => $v)<option value="{{ $k }}" @selected($u->role === $k)>{{ $v }}</option>@endforeach</select>
                    <select name="active"><option value="1" @selected($u->active)>Can sign in</option><option value="0" @selected(! $u->active)>Switched off</option></select>
                    <input type="password" name="password" placeholder="New password (optional)" autocomplete="new-password">
                    <button>Save</button></form></td></tr>
        @endforeach
    </table>
    {{ $users->links() }}
</div>
@endsection
