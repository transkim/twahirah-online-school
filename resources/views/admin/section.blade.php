@extends('layouts.app')
@section('title', $section['label'])
@section('content')
<div class="card" style="padding:28px 30px;max-width:820px">
    <div class="row" style="align-items:center;gap:12px;margin-bottom:10px">
        <h1 style="margin:0">{{ $section['label'] }}</h1>
        <span class="pill" style="background:#fdf0e4;color:#b5600c;border-color:transparent">Coming soon</span>
    </div>
    @if ($section['group'])<div class="muted" style="margin-bottom:14px">{{ $section['group'] }}</div>@endif
    <p style="font-size:15.5px;margin:0 0 16px">{{ $section['about'] }}</p>
    @if ($section['features'])
        <h2>What it will do</h2>
        <ul style="margin:0 0 18px;padding-left:20px">
            @foreach ($section['features'] as $feature)<li style="margin:4px 0">{{ $feature }}</li>@endforeach
        </ul>
    @endif
    <p class="muted" style="margin:0 0 18px">This part of the old portal is being rebuilt. Until then, the working sections are Dashboard, Students, Teachers, Courses, Attendance and Subscription Plans.</p>
    <a class="btn" href="{{ route('admin.dashboard') }}">Back to dashboard</a>
</div>
@endsection
