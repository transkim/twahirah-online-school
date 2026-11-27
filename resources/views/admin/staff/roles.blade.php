@extends('layouts.app')
@section('title', 'Roles')
@section('content')
@php $roles = [
    'admin' => ['Administrator', 'Everything: students, teachers, courses, money, exams, settings, staff accounts and system logs. Approves requisitions and leave.'],
    'finance' => ['Finance', 'Students, payments, subscriptions, discounts, accounts, reports and requisitions. Cannot change settings or staff accounts.'],
    'teacher' => ['Teacher', 'Their own students and classes: live classes and free hours, attendance, lessons, homework, exams, marks, chat, work plans and leave.'],
    'student' => ['Student', 'Their own courses: live classes, lessons, homework, exams, results, chat, the AI helper and their subscription.'],
]; @endphp
<div class="card">
    <h2>What each role can do</h2>
    <table>
        @foreach ($roles as $key => [$name, $what])
            <tr><td><b>{{ $name }}</b><br><span class="muted">{{ $counts[$key] ?? 0 }} people</span></td><td>{{ $what }}</td></tr>
        @endforeach
    </table>
    <p class="muted">Change a person's role in <a href="{{ route('admin.users.index') }}">System Users</a>.</p>
</div>
@endsection
