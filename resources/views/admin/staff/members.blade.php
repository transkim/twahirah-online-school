@extends('layouts.app')
@section('title', 'Members')
@section('content')
<div class="card">
    <h2>Staff and teachers ({{ $staff->count() }})</h2>
    <table>
        <tr><th>Name</th><th>Role</th><th>Department, job and phone</th></tr>
        @foreach ($staff as $u)
            <tr><td>{{ $u->name }}</td><td class="muted">{{ \App\Http\Controllers\Admin\UserController::ROLES[$u->role] ?? $u->role }}</td>
                <td><form class="row" method="post" action="{{ route('admin.staff.members.update', $u) }}">@csrf @method('put')
                    <select name="department_id"><option value="">No department</option>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected($u->department_id === $d->id)>{{ $d->name }}</option>@endforeach</select>
                    <input name="job_title" value="{{ $u->job_title }}" placeholder="Job title">
                    <input name="phone" value="{{ $u->phone }}" placeholder="Phone" style="width:130px">
                    <button>Save</button></form></td></tr>
        @endforeach
    </table>
    <p class="muted">To add someone or let them sign in, use <a href="{{ route('admin.users.index') }}">System Users</a>.</p>
</div>
@endsection
