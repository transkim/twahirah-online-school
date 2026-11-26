<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Home') · {{ config('school.name') }}</title>
    <link rel="icon" href="{{ asset('images/icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eceef1; --card:#fff; --ink:#1a2e0e; --muted:#66755c; --line:#e1e8da; --field:#f5f7f2; --brand:#1a3d12; --brand-dark:#1a3d12; --orange:#e8751a; --orange-light:#f09030; --gold:#f5c200; --warn:#b5600c; --bad:#c9302c; --soft:#eef5ea; }
        :root:not([data-theme="light"]) { color-scheme: light; }
        @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --bg:#0f1a0c; --card:#17241300; --card:#172413; --ink:#e6efe1; --muted:#9db095; --line:#2a3a24; --field:#0f1a0c; --brand:#5fbf4a; --soft:#1d2e18; --warn:#f0a050; --bad:#ff8a80; color-scheme: dark; } }
        :root[data-theme="dark"] { --bg:#0f1a0c; --card:#172413; --ink:#e6efe1; --muted:#9db095; --line:#2a3a24; --field:#0f1a0c; --brand:#5fbf4a; --soft:#1d2e18; --warn:#f0a050; --bad:#ff8a80; }
        * { box-sizing:border-box; }
        body { margin:0; font:14.5px/1.55 'Open Sans', system-ui, sans-serif; background:var(--bg); color:var(--ink); }
        a { color:var(--brand); font-weight:600; text-decoration:none; } a:hover { text-decoration:underline; }
        .shell { min-height:100vh; display:grid; grid-template-columns:260px 1fr; grid-template-rows:auto 1fr; grid-template-areas:"top top" "side body"; }
        .top { grid-area:top; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 20px; background:#fff; border-top:3px solid #1a3d12; border-bottom:1px solid #e6e9ec; }
        .logo { display:flex; align-items:center; gap:10px; color:#2d8c1e; font-weight:700; font-size:20px; letter-spacing:-.2px; }
        .logo img { height:38px; } .logo:hover { text-decoration:none; }
        .who { display:flex; align-items:center; gap:10px; color:#222; font-size:13px; }
        .who b { display:block; font-weight:600; } .who small { display:block; color:#8a9099; font-size:11.5px; }
        .avatar { width:38px; height:38px; border-radius:50%; display:grid; place-items:center; background:#e8efe5; color:#1a3d12; font-weight:800; }
        button.ghost { background:#fff; color:#1a3d12; border:1px solid #d6dbd2; box-shadow:none; min-height:34px; padding:5px 12px; }
        .side { grid-area:side; background:#24402b; padding:18px 0; }
        .side nav { display:flex; flex-direction:column; }
        .side a { display:flex; align-items:center; gap:14px; color:#dfe7dc; font-weight:500; padding:12px 24px; border-left:3px solid transparent; }
        .side a svg { width:18px; height:18px; fill:#b8c7b3; flex:none; }
        .side a:hover { background:rgba(255,255,255,.06); text-decoration:none; }
        .side a.on { border-left-color:#e8751a; background:rgba(0,0,0,.18); color:#fff; } .side a.on svg { fill:#fff; }
        .body { grid-area:body; min-width:0; background:var(--bg); }
        .titlebar { position:relative; display:flex; justify-content:space-between; align-items:center; height:50px; padding:0 20px; background:#34495e; color:#fff; font-size:19px; }
        .titlebar::after { content:''; position:absolute; left:0; bottom:-4px; width:140px; height:4px; background:#e8751a; }
        .titlebar .crumb { font-size:13.5px; color:#d5dde5; }
        @media (max-width: 860px) {
            .shell { grid-template-columns:1fr; grid-template-areas:"top" "side" "body"; }
            .side { padding:0; overflow-x:auto; } .side nav { flex-direction:row; }
            .side a { padding:11px 14px; border-left:0; border-bottom:3px solid transparent; white-space:nowrap; } .side a.on { border-bottom-color:#e8751a; }
            .logo span { font-size:16px; } .who b, .who small { display:none; }
        }
        main { max-width:1180px; padding:26px 24px 56px; }
        h1 { font-size:22px; font-weight:700; margin:0 0 18px; color:var(--ink); }
        h2 { font-size:15.5px; font-weight:700; margin:0 0 12px; color:var(--ink); }
        .card { background:var(--card); border:1px solid #e6e9ec; border-radius:8px; padding:18px 20px; margin-bottom:18px; overflow-x:auto; }
        .grid { display:grid; gap:16px; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); }
        .stat { border-left:4px solid #2d8c1e; padding:14px 18px; }
        .stat.red { border-left-color:#c0392b; } .stat.orange { border-left-color:#e8751a; } .stat.purple { border-left-color:#8e44ad; } .stat.slate { border-left-color:#34495e; }
        .stat span { display:block; text-transform:uppercase; letter-spacing:.5px; font-size:11.5px; font-weight:600; color:#4a5560; }
        .stat b { display:block; font-size:24px; font-weight:700; color:#2d8c1e; font-variant-numeric:tabular-nums; margin-top:2px; }
        .stat.red b { color:#c0392b; } .stat.orange b { color:#e8751a; } .stat.purple b { color:#5b2c6f; } .stat.slate b { color:#34495e; }
        @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) .stat b { color:var(--brand); } }
        
        table { width:100%; border-collapse:collapse; } th, td { text-align:left; padding:9px 8px; border-bottom:1px solid var(--line); vertical-align:top; }
        th { color:var(--muted); font-weight:700; font-size:11.5px; text-transform:uppercase; letter-spacing:.5px; }
        tr:hover td { background:var(--soft); }
        .muted { color:var(--muted); } .warn { color:var(--warn); font-weight:600; } .bad { color:var(--bad); }
        .pill { display:inline-block; padding:1px 9px; border-radius:999px; font-size:12px; font-weight:600; border:1px solid var(--line); color:var(--muted); }
        .pill.ok { background:var(--soft); color:var(--brand); border-color:transparent; }
        form.inline { display:inline; }
        .row { display:flex; gap:8px; flex-wrap:wrap; align-items:end; }
        label { display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); margin-bottom:5px; }
        input, select, textarea { font:inherit; padding:8px 10px; min-height:40px; border:1.5px solid var(--line); border-radius:8px; background:var(--field); color:var(--ink); max-width:100%; outline:none; }
        input:focus, select:focus, textarea:focus { border-color:var(--brand); box-shadow:0 0 0 3px rgba(45, 140, 30, .12); }
        input[type=checkbox] { min-height:0; accent-color:var(--brand); }
        button { font:inherit; font-weight:700; padding:8px 16px; min-height:40px; border:0; border-radius:8px; color:#fff; cursor:pointer;
            background:#1a3d12; box-shadow:none; }
        button.warn-btn { background:var(--orange); }
        a.btn { display:inline-flex; align-items:center; min-height:38px; padding:7px 15px; border-radius:7px; background:#1a3d12; color:#fff; font-weight:600; }
        a.btn.light { background:#fff; color:#1a3d12; border:1px solid #d6dbd2; } a.btn:hover { text-decoration:none; filter:brightness(1.08); }
        button:hover { filter:brightness(1.05); }
        button:focus-visible { outline:3px solid rgba(45, 140, 30, .4); outline-offset:2px; }
        button.plain { background:none; box-shadow:none; color:var(--bad); padding:2px 6px; min-height:0; }
        .flash { background:var(--card); border:1px solid var(--line); border-left:4px solid var(--brand); padding:11px 14px; margin-bottom:18px; border-radius:8px; }
        .errors { border-left-color:var(--bad); color:var(--bad); }
    </style>
</head>
<body>
@auth
@php
    $me = auth()->user();
    $staff = in_array($me->role, ['admin', 'finance']);
    $icons = [
        'home' => 'M12 3 2 12h3v8h6v-6h2v6h6v-8h3L12 3Z',
        'students' => 'M12 3 1 9l11 6 9-4.9V17h2V9L12 3Zm-6 9.2V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-3.8l-6 3.3-6-3.3Z',
        'teachers' => 'M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 2c-2.3 0-7 1.2-7 3.5V19h14v-2.5C15 14.2 10.3 13 8 13Zm8 0c-.3 0-.6 0-1 .1 1.2.8 2 1.9 2 3.4V19h6v-2.5c0-2.3-4.7-3.5-7-3.5Z',
        'plans' => 'M3 6h18v12H3V6Zm2 3v2h14V9H5Zm0 4v3h6v-3H5Z',
        'attendance' => 'M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 16H5V9h14v11Zm-8.5-2.5L7 14l1.4-1.4 2.1 2.1 5.1-5.1L17 11l-6.5 6.5Z',
    ];
    $links = $staff
        ? [['admin.dashboard', 'admin.dashboard', 'Dashboard', 'home'], ['admin.students.index', 'admin.students.*', 'Students', 'students'], ['admin.teachers.index', 'admin.teachers.*', 'Teachers', 'teachers'], ['admin.plans.index', 'admin.plans.*', 'Plans & prices', 'plans']]
        : [['home', 'home', 'Home', 'home']];
    if ($me->role !== 'student') {
        $links[] = ['admin.attendance', 'admin.attendance', 'Attendance', 'attendance'];
    }
@endphp
<div class="shell">
    <header class="top">
        <a class="logo" href="{{ route('home') }}"><img src="{{ asset('images/logo.png') }}" alt=""><span>Twahirah Islamic Center</span></a>
        <div class="who">
            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
            <span><b>{{ $me->name }}</b><small>{{ ['admin' => 'Administrator', 'finance' => 'Finance', 'teacher' => 'Teacher', 'student' => 'Student'][$me->role] ?? $me->role }}</small></span>
            <form method="post" action="{{ route('logout') }}">@csrf <button class="ghost">Sign out</button></form>
        </div>
    </header>
    <aside class="side">
        <nav aria-label="Main">
            @foreach ($links as [$route, $pattern, $label, $icon])
                <a href="{{ route($route) }}" @class(['on' => request()->routeIs($pattern)])>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons[$icon] }}"/></svg>{{ $label }}
                </a>
            @endforeach
        </nav>
    </aside>
    <div class="body">
        <div class="titlebar"><span>@yield('title', 'Home')</span><span class="crumb">{{ config('school.name') }}</span></div>
        <main>
            @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="flash errors">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
            @yield('content')
        </main>
    </div>
</div>
@else
<main>@yield('content')</main>
@endauth
</body>
</html>
