<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'TRUSTEPS CMS Lab' }}</title>
    <style>
        /* ─── Design tokens ───────────────────────────────────── */
        :root {
            color-scheme: light;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --bg:           #f1f3f7;
            --card:         #ffffff;
            --line:         #e2e6ed;
            --text:         #0f172a;
            --muted:        #64748b;
            --primary:      #2563eb;
            --primary-dark: #1d4ed8;
            --danger:       #dc2626;
            --danger-soft:  #fef2f2;
            --success:      #16a34a;
            --success-soft: #f0fdf4;
            --radius:       10px;
            --radius-sm:    6px;
        }

        /* ─── Reset ───────────────────────────────────────────── */
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; }
        body { background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; letter-spacing: .005em; }
        a { color: inherit; }

        /* ─── Page shell ──────────────────────────────────────── */
        .page { min-height: 100vh; display: flex; flex-direction: column; }
        .content { width: min(1280px, calc(100% - 32px)); margin: 24px auto 48px; flex: 1; }

        /* ─── Topbar / Nav ────────────────────────────────────── */
        .topbar {
            min-height: 52px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
            padding: 0 24px;
            background: #fff;
            border-bottom: 1px solid var(--line);
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            white-space: nowrap;
        }
        .brand::before {
            content: "";
            width: 20px;
            height: 20px;
            border-radius: 5px;
            background: var(--primary);
            flex: 0 0 auto;
        }
        .nav { display: flex; align-items: center; gap: 2px; flex-wrap: wrap; justify-content: center; }
        .nav-link {
            display: inline-flex;
            align-items: center;
            height: 32px;
            padding: 0 11px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
            transition: background .1s, color .1s;
            white-space: nowrap;
        }
        .nav-link:hover { background: #f1f3f7; color: var(--text); }
        .nav-link.active { background: var(--primary); color: #fff; }
        .topbar-right { display: flex; align-items: center; justify-content: flex-end; gap: 8px; white-space: nowrap; }
        .user-chip {
            display: inline-flex;
            align-items: center;
            height: 28px;
            padding: 0 10px;
            border-radius: var(--radius-sm);
            background: #f1f3f7;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--line);
        }

        /* ─── Card ────────────────────────────────────────────── */
        .card, .section-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 20px;
        }
        .card + .card, .section-card + .section-card { margin-top: 12px; }
        .mini-card { background: #f8fafc; border: 1px solid var(--line); border-radius: var(--radius); padding: 16px; }

        /* ─── Button ──────────────────────────────────────────── */
        .button {
            appearance: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 36px;
            padding: 0 16px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: var(--primary);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .01em;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            transition: background .1s, border-color .1s;
        }
        .button:hover { background: var(--primary-dark); }
        .button:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,.25); }
        .button:disabled, .button.disabled { opacity: .5; cursor: not-allowed; }
        .button.light { background: #fff; color: var(--text); border-color: var(--line); }
        .button.light:hover { background: #f8fafc; border-color: #c4cdd8; }
        .button.secondary, .button.dark { background: #0f172a; color: #fff; border-color: transparent; }
        .button.secondary:hover, .button.dark:hover { background: #1e293b; }
        .button.danger { background: var(--danger); color: #fff; }
        .button.danger:hover { background: #b91c1c; }
        .button.small { height: 30px; padding: 0 10px; font-size: 12px; border-radius: var(--radius-sm); }
        .actions .button, .form-actions .button { white-space: nowrap; }

        /* ─── Forms ───────────────────────────────────────────── */
        .form-wrap { width: min(460px, calc(100% - 32px)); margin: 64px auto; }
        .field { margin-bottom: 14px; }
        label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px; color: var(--text); }
        input[type="email"], input[type="password"], input[type="text"], select, textarea {
            width: 100%;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            padding: 7px 11px;
            font-size: 14px;
            outline: none;
            background: #fff;
            color: var(--text);
            transition: border-color .1s;
        }
        textarea { min-height: 80px; resize: vertical; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .field label { display: inline-flex; align-items: center; gap: 6px; color: var(--text); font-size: 13px; font-weight: 600; }
        .field.required label::after {
            content: "必須";
            display: inline-flex;
            align-items: center;
            height: 18px;
            padding: 0 5px;
            border-radius: 3px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 10px;
            font-weight: 700;
        }
        .field-hint { margin: 4px 0 0; color: var(--muted); font-size: 12px; line-height: 1.5; }
        .form-shell { display: grid; gap: 12px; margin-top: 16px; }
        .form-section { border: 1px solid var(--line); border-radius: var(--radius); padding: 18px; background: var(--card); }
        .form-section.compact { padding: 14px; }
        .form-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
        .form-section-title { margin: 0; font-size: 15px; font-weight: 700; }
        .form-section-copy { margin: 4px 0 0; color: var(--muted); font-size: 13px; line-height: 1.6; max-width: 720px; }
        .form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
        }
        .form-actions.sticky-ish {
            position: sticky;
            bottom: 0;
            z-index: 5;
            margin: 18px -18px -18px;
            padding: 12px 18px;
            border-radius: 0 0 var(--radius) var(--radius);
            background: rgba(255,255,255,.94);
            backdrop-filter: blur(6px);
        }
        .form-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px; margin-top: 12px; }
        .form-summary-item { border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 10px 12px; background: #f8fafc; }
        .form-summary-item .label { color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .form-summary-item .value { margin-top: 4px; font-weight: 700; font-size: 13px; overflow-wrap: anywhere; }
        .form-note { border: 1px solid #bfdbfe; border-radius: var(--radius-sm); background: #eff6ff; color: #1e3a8a; padding: 10px 12px; line-height: 1.6; font-size: 13px; }

        /* ─── Typography helpers ──────────────────────────────── */
        .muted { color: var(--muted); }
        .muted a { color: var(--primary); text-decoration-thickness: 1px; text-underline-offset: 3px; }
        .page-kicker { margin: 0 0 5px; color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
        .page-title { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -.02em; line-height: 1.2; }
        .page-subtitle { margin: 8px 0 0; color: var(--muted); font-size: 13px; max-width: 760px; line-height: 1.65; }
        .section-label { margin: 0; color: #94a3b8; font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }

        /* ─── Badge ───────────────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            border-radius: 4px;
            padding: 2px 7px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .02em;
            white-space: nowrap;
            border: 1px solid transparent;
        }
        .badge.blue  { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
        .badge.green { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
        .badge.red   { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
        .badge.amber { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .badge.gray  { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        a.badge { text-decoration: none; transition: opacity .1s; }
        a.badge:hover { opacity: .8; }
        a.badge:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,.25); }

        /* ─── Table ───────────────────────────────────────────── */
        .table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: var(--radius); background: #fff; }
        .table-wrap table { width: 100%; border-collapse: collapse; }
        .table-wrap th, .table-wrap td { padding: 10px 14px; text-align: left; vertical-align: middle; border-bottom: 1px solid var(--line); line-height: 1.5; }
        .table-wrap th { background: #f8fafc; color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
        .table-wrap tbody tr:last-child td { border-bottom: none; }
        .table-wrap tbody tr:hover td { background: #f8fafc; }
        .table-wrap td.tight, .table-wrap th.tight { width: 1%; white-space: nowrap; }
        .table-wrap td .actions { justify-content: flex-start; }

        /* ─── Alerts ──────────────────────────────────────────── */
        .error  { background: var(--danger-soft); color: #991b1b; border: 1px solid #fecaca; border-radius: var(--radius); padding: 11px 14px; margin-bottom: 14px; font-size: 13px; }
        .status { background: var(--success-soft); color: #166534; border: 1px solid #86efac; border-radius: var(--radius); padding: 11px 14px; margin-bottom: 14px; font-size: 13px; }
        .alert-box { display: flex; gap: 10px; align-items: flex-start; border-radius: var(--radius); padding: 11px 14px; margin-bottom: 14px; font-size: 13px; line-height: 1.6; }
        .alert-box::before { width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; flex: 0 0 auto; font-weight: 700; font-size: 12px; }
        .alert-box.error  { background: var(--danger-soft); color: #991b1b; border: 1px solid #fecaca; }
        .alert-box.error::before  { content: "!"; background: #fee2e2; color: #991b1b; }
        .alert-box.status { background: var(--success-soft); color: #166534; border: 1px solid #86efac; }
        .alert-box.status::before { content: "✓"; background: #dcfce7; color: #166534; }
        .alert-title { font-weight: 700; margin-bottom: 2px; }

        /* ─── Layout helpers ──────────────────────────────────── */
        .row { display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }

        /* ─── Page header ─────────────────────────────────────── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; }
        .page-header-left { flex: 1; min-width: 0; }
        .page-header-right { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        /* ─── Filter bar ──────────────────────────────────────── */
        .filter-bar { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-bottom: 14px; }
        .filter-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--muted);
            text-decoration: none;
            cursor: pointer;
            transition: background .1s, color .1s, border-color .1s;
            white-space: nowrap;
        }
        .filter-pill:hover { background: #f1f3f7; color: var(--text); }
        .filter-pill.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* ─── Empty state ─────────────────────────────────────── */
        .empty-state { text-align: center; padding: 24px 16px; color: var(--muted); }
        .empty-state-box { max-width: 480px; margin: 0 auto; border: 1px dashed #cbd5e1; border-radius: var(--radius); padding: 24px; background: #fff; }
        .empty-icon { width: 36px; height: 36px; border-radius: var(--radius-sm); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px; background: #eff6ff; color: var(--primary); font-weight: 700; font-size: 13px; }
        .empty-title { margin: 0; color: var(--text); font-size: 16px; font-weight: 700; }
        .empty-copy { margin: 6px auto 0; max-width: 400px; font-size: 13px; line-height: 1.65; }
        .empty-actions { margin-top: 14px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }

        /* ─── Help panel ──────────────────────────────────────── */
        .help-panel { border: 1px solid var(--line); border-radius: var(--radius); background: #f8fafc; overflow: hidden; }
        .help-panel > summary { cursor: pointer; list-style: none; padding: 10px 14px; color: var(--text); font-weight: 600; font-size: 13px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .help-panel > summary::-webkit-details-marker { display: none; }
        .help-panel > summary::after { content: "+"; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; background: #fff; border: 1px solid var(--line); color: var(--muted); flex: 0 0 auto; font-size: 13px; }
        .help-panel[open] > summary::after { content: "−"; }
        .help-body { border-top: 1px solid var(--line); padding: 10px 14px 12px; color: var(--muted); font-size: 13px; line-height: 1.7; }

        /* ─── Info / misc ─────────────────────────────────────── */
        .info-strip { border: 1px solid var(--line); border-radius: var(--radius); padding: 12px 14px; background: #f8fafc; font-size: 13px; }
        .domain-chip { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; overflow-wrap: anywhere; }

        /* ─── Auth pages ──────────────────────────────────────── */
        .auth-shell { width: min(1040px, calc(100% - 32px)); margin: 64px auto; display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(360px, .95fr); gap: 16px; align-items: stretch; }
        .auth-hero {
            position: relative;
            overflow: hidden;
            min-height: 480px;
            padding: 32px;
            border-radius: var(--radius);
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #1d4ed8 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .auth-hero::after { content: ""; position: absolute; right: -60px; bottom: -60px; width: 200px; height: 200px; border-radius: 999px; background: rgba(255,255,255,.06); }
        .auth-logo { display: inline-flex; align-items: center; gap: 10px; font-weight: 700; letter-spacing: .02em; }
        .auth-logo::before { content: ""; width: 26px; height: 26px; border-radius: 6px; background: rgba(255,255,255,.9); }
        .auth-title { margin: 0; font-size: clamp(30px, 4vw, 50px); line-height: 1.05; letter-spacing: -.04em; }
        .auth-copy { margin: 12px 0 0; max-width: 520px; color: rgba(255,255,255,.75); line-height: 1.75; font-size: 14px; }
        .auth-meta { display: flex; flex-wrap: wrap; gap: 6px; position: relative; z-index: 1; }
        .auth-meta span { border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.1); color: rgba(255,255,255,.85); padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .auth-card { align-self: center; padding: 28px; border-radius: var(--radius); }
        .auth-card h1 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -.02em; }
        .auth-card .button { width: 100%; }

        /* ─── Responsive ──────────────────────────────────────── */
        @media (max-width: 980px) {
            .topbar { grid-template-columns: 1fr; align-items: stretch; padding: 6px 16px; min-height: auto; }
            .nav { justify-content: flex-start; }
            .topbar-right { justify-content: space-between; }
        }
        @media (max-width: 900px) {
            .auth-shell { grid-template-columns: 1fr; margin: 24px auto; }
            .auth-hero { min-height: 280px; }
            .auth-card { align-self: stretch; }
        }
        @media (max-width: 720px) {
            .content { width: min(100%, calc(100% - 20px)); margin: 14px auto 32px; }
            .card { padding: 16px; }
            .topbar { padding: 6px 12px; }
            .nav-link { font-size: 12px; padding: 0 8px; }
            .user-chip { display: none; }
            .auth-shell { width: min(100%, calc(100% - 20px)); }
            .auth-hero, .auth-card { border-radius: 8px; padding: 20px; }
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
                <a class="nav-link {{ request()->routeIs('bizmaps.*') ? 'active' : '' }}" href="{{ route('bizmaps.import') }}">
                    BIZMAPSインポート
                </a>
                <a class="nav-link {{ request()->routeIs('industries.scores.*') ? 'active' : '' }}" href="{{ route('industries.scores.index') }}">
                    業界スコア
                </a>
                <a class="nav-link {{ request()->routeIs('system.reset-mvp-data.*') ? 'active' : '' }}" href="{{ route('system.reset-mvp-data.index') }}">
                    MVPリセット
                </a>
                <a class="nav-link {{ request()->routeIs('companies.index') || request()->routeIs('companies.show') || request()->routeIs('companies.merge-form') ? 'active' : '' }}" href="{{ route('companies.index') }}">
                    companies
                </a>
                <a class="nav-link {{ request()->routeIs('companies.candidates') ? 'active' : '' }}" href="{{ route('companies.candidates') }}">
                    営業候補
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
@stack('scripts')
</body>
</html>
