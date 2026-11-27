@extends('layouts.app')
@section('title', 'Automatic Messages')
@section('content')
<div class="card">
    <h2>Messages the system sends by itself</h2>
    <table>
        <tr><th>When</th><th>Who gets it</th><th>How</th></tr>
        <tr><td>{{ config('school.reminder_days') }} days before a plan ends</td><td>The student</td><td>App bell and SMS</td></tr>
        <tr><td>A teacher adds a lesson or homework</td><td>Students on that course</td><td>App bell</td></tr>
        <tr><td>Homework or an exam is marked</td><td>The student</td><td>App bell</td></tr>
        <tr><td>A student books a live class</td><td>The teacher</td><td>App bell</td></tr>
        <tr><td>Leave or a requisition is decided</td><td>The person who asked</td><td>App bell</td></tr>
        <tr><td>An announcement or event is sent</td><td>Who it is for</td><td>App bell, and SMS if ticked</td></tr>
        <tr><td>First sign-in or forgotten password</td><td>The person</td><td>SMS code</td></tr>
    </table>
    <p class="muted">Change the reminder days in <a href="{{ route('admin.settings') }}">System Settings</a>. SMS go out once the school's SMS account is connected; until then they are written to the server log.</p>
</div>
@endsection
