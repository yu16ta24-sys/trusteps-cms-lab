<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'TRUSTEPS CMS Lab' }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f6f8;
            color: #172033;
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f4f6f8; }
        a { color: inherit; }
        .page { min-height: 100vh; display: flex; flex-direction: column; }
        .topbar {
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 0 28px;
            background: #111827;
            color: #fff;
            box-shadow: 0 2px 12px rgba(15, 23, 42, .15);
        }
        .brand { font-weight: 700; letter-spacing: .02em; white-space: nowrap; }
        .nav { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .nav a {
            color: #dbeafe;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
        }
        .content { width: min(1180px, calc(100% - 32px)); margin: 32px auto; flex: 1; }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 16px 45px rgba(15, 23, 42, .06);
            padding: 28px;
        }
        .muted { color: #667085; }
        .button {
            appearance: none;
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            padding: 11px 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .button.secondary { background: #374151; }
        .button.light { background: #e5e7eb; color: #111827; }
        .button.danger { background: #dc2626; }
        .button.small { padding: 8px 10px; border-radius: 8px; font-size: 13px; }
        .form-wrap { width: min(460px, calc(100% - 32px)); margin: 72px auto; }
        .field { margin-bottom: 18px; }
        label { display: block; font-weight: 700; margin-bottom: 8px; }
        input[type="email"], input[type="password"], input[type="text"], input[type="url"], input[type="file"], select, textarea {
            width: 100%;
            border: 1px solid #d0d5dd;
            border-radius: 10px;
            padding: 12px 13px;
            font-size: 16px;
            outline: none;
            background: #fff;
        }
        textarea { min-height: 120px; resize: vertical; }
        input:focus, select:focus, textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, .12); }
        .error, .status {
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 18px;
        }
        .error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .status { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .row { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .mini-card { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px; }
        .mini-card strong { display: block; margin-bottom: 8px; }
        .table-wrap { overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 12px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 12px 14px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; font-size: 14px; }
        th { background: #f8fafc; font-size: 13px; color: #475467; white-space: nowrap; }
        tr:last-child td { border-bottom: 0; }
        code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #0f172a;
            color: #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            overflow: auto;
        }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .pagination { margin-top: 18px; }
        .pagination nav > div:first-child { display: none; }
    </style>
</head>
<body>
<div class="page">
    @auth
        <header class="topbar">
            <div class="row" style="gap:22px;">
                <div class="brand">TRUSTEPS CMS Lab</div>
                <nav class="nav">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <a href="{{ route('source-records.index') }}">Source Records</a>
                </nav>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button secondary" type="submit">ログアウト</button>
            </form>
        </header>
    @endauth

    @yield('content')
</div>
</body>
</html>
