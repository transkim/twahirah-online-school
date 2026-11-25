<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Home') · {{ config('school.name') }}</title>
    <style>
        :root { --bg:#f6f5f1; --card:#fff; --ink:#1d2a24; --muted:#5f6b65; --line:#e3e1da; --brand:#0f6b4f; --brand-ink:#fff; --warn:#a35b00; --bad:#b3261e; }
        @media (prefers-color-scheme: dark) { :root { --bg:#121815; --card:#1a221e; --ink:#e7ece9; --muted:#9aa7a0; --line:#2b3530; --brand:#3fae86; --brand-ink:#0b1410; --warn:#f0b35a; --bad:#ff8a80; } }
        * { box-sizing: border-box; }
        body { margin:0; font:15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:var(--bg); color:var(--ink); }
        a { color:var(--brand); }
        header { background:var(--card); border-bottom:1px solid var(--line); }
        .bar { max-width:1100px; margin:0 auto; padding:10px 16px; display:flex; gap:16px; align-items:center; flex-wrap:wrap; }
        .bar strong { margin-right:auto; }
        nav a { text-decoration:none; color:var(--ink); padding:4px 8px; border-radius:6px; }
        nav a.on { background:var(--brand); color:var(--brand-ink); }
        main { max-width:1100px; margin:0 auto; padding:20px 16px 48px; }
        h1 { font-size:22px; margin:0 0 16px; } h2 { font-size:17px; margin:0 0 12px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:16px; margin-bottom:16px; overflow-x:auto; }
        .grid { display:grid; gap:16px; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); }
        .stat b { display:block; font-size:26px; } .stat span { color:var(--muted); }
        table { width:100%; border-collapse:collapse; } th, td { text-align:left; padding:8px; border-bottom:1px solid var(--line); vertical-align:top; }
        th { color:var(--muted); font-weight:600; font-size:13px; }
        .muted { color:var(--muted); } .warn { color:var(--warn); } .bad { color:var(--bad); }
        .pill { display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; border:1px solid var(--line); }
        .pill.ok { background:var(--brand); color:var(--brand-ink); border-color:var(--brand); }
        form.inline { display:inline; }
        .row { display:flex; gap:8px; flex-wrap:wrap; align-items:end; }
        label { display:block; font-size:13px; color:var(--muted); margin-bottom:4px; }
        input, select, textarea { font:inherit; padding:7px 9px; border:1px solid var(--line); border-radius:6px; background:var(--bg); color:var(--ink); max-width:100%; }
        button { font:inherit; padding:7px 14px; border:0; border-radius:6px; background:var(--brand); color:var(--brand-ink); cursor:pointer; }
        button.plain { background:none; color:var(--bad); padding:2px 6px; }
        .flash { background:var(--card); border-left:4px solid var(--brand); padding:10px 14px; margin-bottom:16px; border-radius:6px; }
        .errors { border-left-color:var(--bad); }
    </style>
</head>
<body>
@auth
<header>
    <div class="bar">
        <strong>{{ config('school.name') }}</strong>
        <nav>
            @if (in_array(auth()->user()->role, ['admin', 'finance']))
                <a href="{{ route('admin.dashboard') }}" @class(['on' => request()->routeIs('admin.dashboard')])>Dashboard</a>
                <a href="{{ route('admin.students.index') }}" @class(['on' => request()->routeIs('admin.students.*')])>Students</a>
                <a href="{{ route('admin.teachers.index') }}" @class(['on' => request()->routeIs('admin.teachers.*')])>Teachers</a>
                <a href="{{ route('admin.plans.index') }}" @class(['on' => request()->routeIs('admin.plans.*')])>Plans</a>
            @else
                <a href="{{ route('home') }}" @class(['on' => request()->routeIs('home')])>Home</a>
            @endif
            @if (auth()->user()->role !== 'student')
                <a href="{{ route('admin.attendance') }}" @class(['on' => request()->routeIs('admin.attendance')])>Attendance</a>
            @endif
        </nav>
        <form class="inline" method="post" action="{{ route('logout') }}">@csrf <button>Sign out</button></form>
    </div>
</header>
@endauth
<main>
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="flash errors">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @yield('content')
</main>
</body>
</html>
