<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('school.name') }}</title>
    <link rel="icon" href="{{ asset('images/icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --green-dark:#14580e; --green:#2d8c1e; --orange:#e8751a; --orange-light:#f09030; --gold:#f5c200; --ink:#1a2e0e; --muted:#66755c; --label:#4a5e40; --field:#f5f7f2; --line:#dfe6d8; --icon:#9aae90; --bad:#c9302c; }
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        html, body { min-height:100%; font-family:'Open Sans', system-ui, sans-serif; color:var(--ink); background:#14240f; }
        .page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:30px 16px;
            background: linear-gradient(rgba(10, 24, 8, .45), rgba(10, 24, 8, .55)), url('{{ asset('images/signin-background.jpg') }}') center / cover no-repeat fixed; }
        .card { position:relative; overflow:hidden; width:100%; max-width:420px; background:#fff; border-radius:14px; padding:46px 42px 34px;
            box-shadow:0 18px 60px rgba(0, 0, 0, .35), 0 2px 10px rgba(0, 0, 0, .08); animation:card-in .35s ease-out both; }
        .card::before { content:''; position:absolute; inset:0 0 auto 0; height:4px; background:linear-gradient(90deg, var(--green-dark) 0%, var(--green) 40%, var(--gold) 70%, var(--orange-light) 100%); }
        @keyframes card-in { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
        @media (prefers-reduced-motion: reduce) { .card { animation:none; } }
        .head { text-align:center; margin-bottom:28px; }
        .head img { height:68px; width:auto; margin-bottom:14px; filter:drop-shadow(0 4px 10px rgba(20, 80, 10, .15)); }
        .brand { font-size:15px; font-weight:800; letter-spacing:1.2px; text-transform:uppercase; color:var(--green-dark); }
        .divider { width:44px; height:3px; border-radius:2px; background:linear-gradient(90deg, var(--gold), var(--orange-light)); margin:12px auto 18px; }
        h1 { font-size:21px; font-weight:700; margin-bottom:4px; }
        .head p { font-size:13px; color:var(--muted); }
        .group { margin-bottom:18px; }
        label.top { display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--label); margin-bottom:7px; }
        .wrap { position:relative; }
        .wrap svg.icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); width:15px; height:15px; fill:var(--icon); pointer-events:none; }
        .wrap input { width:100%; height:48px; padding:0 46px 0 40px; font:inherit; font-size:14px; color:var(--ink); background:var(--field);
            border:1.5px solid var(--line); border-radius:8px; outline:none; transition:border-color .2s, box-shadow .2s, background .2s; }
        .wrap input::placeholder { color:#9aa69a; }
        .wrap input:focus { border-color:var(--green); background:#fff; box-shadow:0 0 0 3px rgba(45, 140, 30, .12); }
        .eye { position:absolute; right:8px; top:50%; transform:translateY(-50%); width:34px; height:34px; border:0; background:none; cursor:pointer; border-radius:6px; display:grid; place-items:center; }
        .eye svg { width:17px; height:17px; fill:var(--icon); }
        .eye:hover svg, .eye:focus-visible svg { fill:var(--green); }
        .row { display:flex; justify-content:space-between; align-items:center; margin:4px 0 22px; font-size:13px; }
        .row label { display:flex; gap:8px; align-items:center; color:#3c4a35; cursor:pointer; }
        .row input { width:16px; height:16px; accent-color:var(--green); }
        .help { color:var(--green); font-weight:600; }
        button.go { width:100%; height:50px; border:0; border-radius:8px; cursor:pointer; font:inherit; font-size:15px; font-weight:700; color:#fff;
            background:linear-gradient(90deg, #c86010, var(--orange) 45%, var(--orange-light)); box-shadow:0 6px 18px rgba(232, 117, 26, .35); transition:transform .15s, box-shadow .15s; }
        button.go:hover { transform:translateY(-1px); box-shadow:0 8px 22px rgba(232, 117, 26, .45); }
        button.go:focus-visible { outline:3px solid rgba(45, 140, 30, .4); outline-offset:2px; }
        .error { background:#fdecea; color:var(--bad); border-left:3px solid var(--bad); padding:10px 12px; border-radius:6px; font-size:13px; margin-bottom:18px; }
        .notice { background:#eef5ea; color:#1f4f17; border-left:3px solid var(--green); padding:10px 12px; border-radius:6px; font-size:13px; margin-bottom:18px; }
        .hint { font-size:12.5px; color:var(--muted); margin-top:6px; }
        .back { display:block; text-align:center; margin-top:16px; font-size:13px; color:var(--green); font-weight:600; text-decoration:none; }
        a.help { text-decoration:none; } a.help:hover, .back:hover { text-decoration:underline; }
        .foot { text-align:center; margin-top:22px; font-size:12px; color:var(--muted); }
        @media (max-width: 480px) { .card { padding:38px 22px 28px; } }
    </style>
</head>
<body>
<div class="page">
    <main class="card">
        <div class="head">
            <img src="{{ asset('images/logo.png') }}" alt="Twahirah Islamic Center logo">
            <div class="brand">{{ config('school.name') }}</div>
            <div class="divider"></div>
            <h1>@yield('heading')</h1>
            <p>@yield('subheading')</p>
        </div>

        @if (session('status'))
            <div class="notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="error" role="alert">{{ $errors->first() }}</div>
        @endif

        @yield('content')
        <p class="foot">Twahirah Islamic Center</p>
    </main>
</div>
</body>
</html>
