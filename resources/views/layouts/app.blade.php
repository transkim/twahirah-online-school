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
        .shell { min-height:100vh; display:grid; grid-template-columns:272px 1fr; grid-template-rows:64px 1fr; grid-template-areas:"top top" "side body"; }
        .top { grid-area:top; position:sticky; top:0; z-index:30; display:flex; align-items:center; gap:16px; padding:0 20px; background:#fff; border-top:3px solid #1a3d12; box-shadow:0 1px 0 #e3e7e0, 0 4px 14px rgba(20,40,15,.05); }
        .logo { display:flex; align-items:center; gap:10px; color:#2d8c1e; font-weight:700; font-size:19px; letter-spacing:-.2px; white-space:nowrap; }
        .logo img { height:40px; } .logo:hover { text-decoration:none; }
        .search { flex:1; max-width:420px; margin-left:28px; position:relative; }
        .search input { width:100%; min-height:38px; padding:7px 12px 7px 36px; border-radius:999px; background:#f3f5f1; border:1px solid #e3e7e0; }
        .search svg { position:absolute; left:12px; top:11px; width:16px; height:16px; fill:#8a9585; }
        .who { margin-left:auto; display:flex; align-items:center; gap:12px; color:#222; font-size:13px; }
        .who b { display:block; font-weight:600; line-height:1.2; } .who small { display:block; color:#8a9099; font-size:11.5px; }
        .avatar { width:38px; height:38px; border-radius:50%; display:grid; place-items:center; background:linear-gradient(135deg,#2d8c1e,#1a3d12); color:#fff; font-weight:700; }
        .bell { position:relative; width:38px; height:38px; border-radius:50%; display:grid; place-items:center; background:#f3f5f1; }
        .bell svg { width:18px; height:18px; fill:#4d5a48; }
        button.ghost { white-space:nowrap; background:#fff; color:#1a3d12; border:1px solid #d6dbd2; box-shadow:none; min-height:34px; padding:5px 12px; }
        .burger { display:none; cursor:pointer; width:38px; height:38px; border-radius:8px; place-items:center; background:#f3f5f1; }
        .burger svg { width:20px; height:20px; fill:#1a3d12; }
        #menu-toggle { display:none; }
        .side { grid-area:side; position:sticky; top:64px; height:calc(100vh - 64px); overflow-y:auto; background:linear-gradient(180deg,#24402b 0%,#1d3523 100%); padding:10px 0 40px; scrollbar-width:thin; scrollbar-color:#3d5c44 transparent; }
        .side .heading { color:#8fa694; font-size:11px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; padding:12px 24px 8px; }
        .side nav { display:flex; flex-direction:column; }
        .side a, .side summary { display:flex; align-items:center; gap:14px; color:#dfe7dc; font-weight:500; font-size:14px; padding:11px 20px 11px 21px; border-left:3px solid transparent; cursor:pointer; list-style:none; user-select:none; }
        .side summary::-webkit-details-marker { display:none; }
        .side svg.i { width:18px; height:18px; fill:#a9bba5; flex:none; }
        .side a:hover, .side summary:hover { background:rgba(255,255,255,.06); text-decoration:none; color:#fff; }
        .side a.on, .side details.on > summary { border-left-color:#e8751a; background:rgba(0,0,0,.2); color:#fff; } .side .on svg.i { fill:#f09030; }
        .side .chev { margin-left:auto; width:14px; height:14px; fill:#8fa694; transition:transform .2s; }
        .side details[open] > summary .chev { transform:rotate(90deg); }
        .side .badge { margin-left:auto; min-width:22px; padding:0 7px; border-radius:999px; background:#e8751a; color:#fff; font-size:11.5px; font-weight:700; text-align:center; line-height:20px; }
        .side .sub { background:rgba(0,0,0,.14); padding:4px 0; }
        .side .sub a { padding:8px 20px 8px 56px; font-size:13.5px; color:#c9d5c5; position:relative; gap:8px; }
        .side .sub a::before { content:''; position:absolute; left:32px; top:50%; width:6px; height:6px; margin-top:-3px; border-radius:50%; border:1.5px solid #7d957f; }
        .side .sub a.on { background:rgba(232,117,26,.12); border-left-color:transparent; color:#fff; } .side .sub a.on::before { background:#f09030; border-color:#f09030; }
        .side .soon { margin-left:auto; font-size:10px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; color:#8fa694; border:1px solid #476149; border-radius:4px; padding:0 5px; line-height:16px; }
        .body { grid-area:body; min-width:0; background:var(--bg); }
        .titlebar { position:relative; display:flex; justify-content:space-between; align-items:center; gap:12px; height:50px; padding:0 24px; background:#34495e; color:#fff; font-size:18px; font-weight:600; }
        .titlebar::after { content:''; position:absolute; left:0; bottom:-4px; width:140px; height:4px; background:#e8751a; }
        .titlebar .crumb { font-size:13px; font-weight:400; color:#c9d3dc; white-space:nowrap; } .titlebar .crumb b { color:#fff; font-weight:600; }
        @media (max-width: 900px) {
            .shell { grid-template-columns:minmax(0, 1fr); grid-template-areas:"top" "body"; }
            .top { padding:0 12px; gap:10px; } .bell { display:none; } .logo img { height:34px; }
            .burger { display:grid; }
            .side { position:fixed; left:0; top:64px; bottom:0; width:280px; height:auto; z-index:40; transform:translateX(-100%); transition:transform .2s; box-shadow:8px 0 24px rgba(0,0,0,.25); }
            #menu-toggle:checked ~ .shell .side { transform:none; }
            .search, .who b, .who small { display:none; } .logo span { font-size:15px; white-space:normal; line-height:1.15; }
            .titlebar .crumb { display:none; } main { padding:20px 16px 48px !important; }
        }
        main { max-width:1240px; padding:28px 28px 56px; }
        h1 { font-size:22px; font-weight:700; margin:0 0 18px; color:var(--ink); }
        h2 { font-size:15.5px; font-weight:700; margin:0 0 12px; color:var(--ink); }
        .card { background:var(--card); border:1px solid #e6e9ec; border-radius:10px; padding:18px 20px; margin-bottom:18px; overflow-x:auto; box-shadow:0 1px 2px rgba(20,40,15,.04), 0 2px 8px rgba(20,40,15,.03); }
        .card > h2:first-child { padding-bottom:10px; border-bottom:1px solid var(--line); }
        .grid { display:grid; gap:16px; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); }
        .stat { position:relative; border-left:4px solid #2d8c1e; padding:16px 18px; margin-bottom:0; --tone:#2d8c1e; }
        .stat.red { --tone:#c0392b; } .stat.orange { --tone:#e8751a; } .stat.purple { --tone:#8e44ad; } .stat.slate { --tone:#34495e; }
        .stat .ic { position:absolute; right:16px; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; display:grid; place-items:center; background:color-mix(in srgb, var(--tone) 12%, transparent); }
        .stat .ic svg { width:22px; height:22px; fill:var(--tone); }
        .stat small { display:block; color:var(--muted); font-size:12px; margin-top:2px; }
        .split { display:grid; gap:18px; grid-template-columns:minmax(0, 1.6fr) minmax(0, 1fr); margin-top:18px; } @media (max-width: 1000px) { .split { grid-template-columns:minmax(0, 1fr); } }
        .quick { display:grid; grid-template-columns:repeat(2, 1fr); gap:10px; }
        .quick a { display:flex; align-items:center; gap:10px; padding:12px; border:1px solid var(--line); border-radius:8px; color:var(--ink); font-weight:600; font-size:13.5px; }
        .quick a:hover { border-color:#2d8c1e; background:var(--soft); text-decoration:none; }
        .quick svg { width:20px; height:20px; fill:#2d8c1e; flex:none; }
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
    $menu = app(\App\Services\Navigation::class)->menuFor($me);
    $icons = config('navigation.icons');
    $activeGroup = collect($menu)->first(fn ($item) => $item['active'] && $item['children']);
@endphp
<input type="checkbox" id="menu-toggle" aria-hidden="true">
<div class="shell">
    <header class="top">
        <label class="burger" for="menu-toggle" aria-label="Menu"><svg viewBox="0 0 24 24"><path d="M3 6h18v2H3V6Zm0 5h18v2H3v-2Zm0 5h18v2H3v-2Z"/></svg></label>
        <a class="logo" href="{{ route('home') }}"><img src="{{ asset('images/logo.png') }}" alt=""><span>Twahirah Islamic Center</span></a>
        @if (in_array($me->role, ['admin', 'finance']))
            <form class="search" method="get" action="{{ route('admin.students.index') }}" role="search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.8l-.3-.3A6.5 6.5 0 1 0 9.5 16a6.5 6.5 0 0 0 4.2-1.6l.3.3v.8l5 5 1.5-1.5-5-5Zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Z"/></svg>
                <input name="q" value="{{ request()->routeIs('admin.students.index') ? request('q') : '' }}" placeholder="Search students by name, phone or number" aria-label="Search students">
            </form>
        @endif
        <div class="who">
            <span class="bell" title="Notifications"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2Zm6-6v-5c0-3.1-1.6-5.6-4.5-6.3V4a1.5 1.5 0 0 0-3 0v.7C7.6 5.4 6 7.9 6 11v5l-2 2v1h16v-1l-2-2Z"/></svg></span>
            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
            <span><b>{{ $me->name }}</b><small>{{ ['admin' => 'Administrator', 'finance' => 'Finance', 'teacher' => 'Teacher', 'student' => 'Student'][$me->role] ?? $me->role }}</small></span>
            <form method="post" action="{{ route('logout') }}">@csrf <button class="ghost">Sign out</button></form>
        </div>
    </header>
    <aside class="side">
        <div class="heading">Navigation</div>
        <nav aria-label="Main">
            @foreach ($menu as $item)
                @if ($item['children'])
                    <details @class(['on' => $item['active']]) @if ($item['active']) open @endif>
                        <summary><svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons[$item['icon']] }}"/></svg>{{ $item['label'] }}<svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M8.6 16.6 13.2 12 8.6 7.4 10 6l6 6-6 6-1.4-1.4Z"/></svg></summary>
                        <div class="sub">
                            @foreach ($item['children'] as $child)
                                <a href="{{ $child['url'] }}" @class(['on' => $child['active']])>{{ $child['label'] }}@if ($child['soon'])<span class="soon">Soon</span>@endif</a>
                            @endforeach
                        </div>
                    </details>
                @else
                    <a href="{{ $item['url'] }}" @class(['on' => $item['active']])>
                        <svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons[$item['icon']] }}"/></svg>{{ $item['label'] }}
                        @if ($item['badge'] !== null)<span class="badge" title="Live classes today">{{ $item['badge'] }}</span>@endif
                    </a>
                @endif
            @endforeach
        </nav>
    </aside>
    <div class="body">
        <div class="titlebar"><span>@yield('title', 'Home')</span><span class="crumb">{{ config('school.name') }}@if ($activeGroup) › {{ $activeGroup['label'] }}@endif › <b>@yield('title', 'Home')</b></span></div>
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
