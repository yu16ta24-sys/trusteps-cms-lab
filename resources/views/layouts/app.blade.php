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
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --bg: #f3f6fb;
            --card: #ffffff;
            --line: #e2e8f0;
            --text: #172033;
            --muted: #667085;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --light: #eef2ff;
            --danger: #dc2626;
            --danger-soft: #fef2f2;
            --success: #16a34a;
            --success-soft: #f0fdf4;
            --shadow: 0 18px 45px rgba(15, 23, 42, .08);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; }
        body {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.08), transparent 32%),
                radial-gradient(circle at top right, rgba(14, 165, 233, 0.08), transparent 24%),
                var(--bg);
            color: var(--text);
        }
        a { color: inherit; }
        .page { min-height: 100vh; display: flex; flex-direction: column; }
        .topbar {
            min-height: 68px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 18px;
            align-items: center;
            padding: 12px 28px;
            background: rgba(17, 24, 39, 0.94);
            color: #fff;
            box-shadow: 0 8px 30px rgba(15, 23, 42, .18);
            backdrop-filter: blur(14px);
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .brand {
            font-weight: 900;
            letter-spacing: .03em;
            white-space: nowrap;
        }
        .nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 9px 13px;
            border-radius: 999px;
            text-decoration: none;
            color: #d1d5db;
            font-size: 14px;
            font-weight: 800;
            border: 1px solid transparent;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
            white-space: nowrap;
        }
        .nav-link:hover {
            background: rgba(255, 255, 255, .08);
            color: #fff;
        }
        .nav-link.active {
            background: #ffffff;
            color: #111827;
            border-color: rgba(255, 255, 255, .18);
        }
        .topbar-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            white-space: nowrap;
        }
        .user-chip {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            padding: 8px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .08);
            color: #e5e7eb;
            font-size: 12px;
            font-weight: 700;
        }
        .content { width: min(1280px, calc(100% - 32px)); margin: 32px auto; flex: 1; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 28px;
        }
        .muted { color: var(--muted); }
        .button {
            appearance: none;
            border: 0;
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            padding: 11px 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
            box-shadow: 0 10px 22px rgba(37, 99, 235, .18);
        }
        .button:hover { transform: translateY(-1px); background: var(--primary-dark); }
        .button.secondary { background: #374151; box-shadow: none; }
        .button.secondary:hover { background: #1f2937; }
        .button.light {
            background: #ffffff;
            color: var(--text);
            border: 1px solid var(--line);
            box-shadow: none;
        }
        .button.light:hover { background: #f8fafc; }
        .button.danger { background: var(--danger); box-shadow: 0 10px 22px rgba(220, 38, 38, .18); }
        .button.danger:hover { background: #b91c1c; }
        .button.small { padding: 8px 12px; border-radius: 10px; font-size: 13px; }
        .form-wrap { width: min(460px, calc(100% - 32px)); margin: 72px auto; }
        .field { margin-bottom: 18px; }
        label { display: block; font-weight: 700; margin-bottom: 8px; }
        input[type="email"], input[type="password"], input[type="text"], select, textarea {
            width: 100%;
            border: 1px solid #d0d5dd;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: 15px;
            outline: none;
            background: #fff;
            color: var(--text);
        }
        textarea { min-height: 96px; resize: vertical; }
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
        }
        .error {
            background: var(--danger-soft);
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .status {
            background: var(--success-soft);
            color: #166534;
            border: 1px solid #86efac;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .row {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .mini-card {
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .02em;
            white-space: nowrap;
        }
        .badge.green { background: #dcfce7; color: #166534; }
        .badge.gray { background: #eef2f7; color: #475467; }
        .badge.red { background: #fee2e2; color: #991b1b; }
        .badge.blue { background: #dbeafe; color: #1d4ed8; }
        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 14px 16px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #edf2f7;
        }
        th {
            color: #475467;
            font-size: 13px;
            font-weight: 800;
            background: #f8fafc;
        }
        tbody tr:last-child td, tbody tr:last-child th { border-bottom: 0; }
        @media (max-width: 980px) {
            .topbar {
                grid-template-columns: 1fr;
                align-items: stretch;
            }
            .nav { justify-content: flex-start; }
            .topbar-right { justify-content: space-between; }
        }
        @media (max-width: 720px) {
            .content { width: min(100%, calc(100% - 20px)); margin: 18px auto; }
            .card { padding: 20px; border-radius: 16px; }
            .topbar { padding: 12px 16px; }
            .nav-link { font-size: 13px; padding: 8px 10px; }
            .user-chip { display: none; }
        }
    </style>
</head>
<body>
<div class="page">
    @auth
        <header class="topbar">
            <div class="brand">TRUSTEPS CMS Lab</div>

            <nav class="nav" aria-label="グローバルナビゲーション">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    Dashboard
                </a>
                <a class="nav-link {{ request()->routeIs('source-records.*') ? 'active' : '' }}" href="{{ route('source-records.index') }}">
                    source_records
                </a>
                <a class="nav-link {{ request()->routeIs('companies.index') || request()->routeIs('companies.show') || request()->routeIs('companies.merge-form') ? 'active' : '' }}" href="{{ route('companies.index') }}">
                    companies
                </a>
                <a class="nav-link {{ request()->routeIs('companies.candidates') ? 'active' : '' }}" href="{{ route('companies.candidates') }}">
                    営業候補
                </a>
                <a class="nav-link {{ request()->routeIs('account.*') ? 'active' : '' }}" href="{{ route('account.edit') }}">
                    アカウント
                </a>
            </nav>

            <div class="topbar-right">
                <span class="user-chip">{{ auth()->user()?->email }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button secondary" type="submit">ログアウト</button>
                </form>
            </div>
        </header>
    @endauth

    @yield('content')
</div>
</body>
</html>
