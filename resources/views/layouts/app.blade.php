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


        /* v0.17.0 visual polish */
        :root {
            --bg: #eef3f8;
            --card: rgba(255, 255, 255, .92);
            --line: #d9e2ee;
            --text: #101828;
            --muted: #667085;
            --primary: #1f5eff;
            --primary-dark: #1749c9;
            --nav-bg: rgba(255, 255, 255, .86);
            --shadow: 0 18px 50px rgba(16, 24, 40, .09);
            --shadow-soft: 0 10px 26px rgba(16, 24, 40, .06);
            --radius-lg: 24px;
            --radius-md: 18px;
        }
        body {
            background:
                radial-gradient(circle at 8% 0%, rgba(31, 94, 255, .12), transparent 34%),
                radial-gradient(circle at 92% 8%, rgba(20, 184, 166, .10), transparent 30%),
                linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
            letter-spacing: .005em;
        }
        .topbar {
            min-height: 72px;
            background: var(--nav-bg);
            color: var(--text);
            border-bottom: 1px solid rgba(217, 226, 238, .85);
            box-shadow: 0 10px 35px rgba(16, 24, 40, .08);
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            color: #0f172a;
        }
        .brand::before {
            content: "";
            width: 28px;
            height: 28px;
            border-radius: 9px;
            background: linear-gradient(135deg, var(--primary), #14b8a6);
            box-shadow: 0 10px 20px rgba(31, 94, 255, .22);
        }
        .nav { gap: 6px; }
        .nav-link {
            color: #475467;
            background: transparent;
            border: 1px solid transparent;
        }
        .nav-link:hover {
            background: rgba(31, 94, 255, .08);
            color: #0f172a;
        }
        .nav-link.active {
            background: #0f172a;
            color: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .16);
        }
        .user-chip {
            background: #f2f4f7;
            color: #475467;
            border: 1px solid #e4e7ec;
        }
        .content { margin: 28px auto 48px; }
        .card, .section-card {
            background: var(--card);
            border-color: rgba(217, 226, 238, .95);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }
        .mini-card, .kv, .score-now {
            border-color: rgba(217, 226, 238, .95) !important;
            box-shadow: var(--shadow-soft);
        }
        .mini-card {
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.92));
        }
        .button {
            border-radius: 999px;
            font-weight: 850;
            box-shadow: 0 12px 26px rgba(31, 94, 255, .18);
        }
        .button.light {
            background: rgba(255,255,255,.78);
            border-color: #d9e2ee;
        }
        .button.secondary {
            background: #0f172a;
            color: #fff;
        }
        input[type="email"], input[type="password"], input[type="text"], select, textarea {
            border-radius: 14px;
            border-color: #d9e2ee;
            background: rgba(255,255,255,.9);
        }
        .table-wrap {
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
        }
        th {
            background: #f9fafb;
            color: #344054;
            letter-spacing: .02em;
        }
        tbody tr { transition: background .12s ease; }
        tbody tr:hover { background: #f8fbff; }
        .badge { font-weight: 850; }
        .page-kicker {
            margin: 0 0 8px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .page-title {
            margin: 0;
            font-size: clamp(30px, 4vw, 44px);
            line-height: 1.05;
            letter-spacing: -.035em;
        }
        .page-subtitle {
            margin: 12px 0 0;
            color: var(--muted);
            max-width: 760px;
            line-height: 1.75;
        }
        .help-panel {
            margin-top: 12px;
            border: 1px solid #d9e2ee;
            border-radius: 16px;
            background: rgba(248, 250, 252, .82);
            overflow: hidden;
        }
        .help-panel > summary {
            cursor: pointer;
            list-style: none;
            padding: 12px 14px;
            color: #344054;
            font-weight: 900;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .help-panel > summary::-webkit-details-marker { display: none; }
        .help-panel > summary::after {
            content: "+";
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #d9e2ee;
            color: #667085;
            flex: 0 0 auto;
        }
        .help-panel[open] > summary::after { content: "−"; }
        .help-body {
            border-top: 1px solid #e4e7ec;
            padding: 12px 14px 14px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.75;
        }
        .info-strip {
            border: 1px solid #d9e2ee;
            border-radius: 18px;
            padding: 14px 16px;
            background: rgba(255,255,255,.72);
        }
        .section-label {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }



        /* v0.17.3 login / feedback / empty-state polish */
        .auth-shell {
            width: min(1040px, calc(100% - 32px));
            margin: 64px auto;
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(360px, .95fr);
            gap: 22px;
            align-items: stretch;
        }
        .auth-hero {
            position: relative;
            overflow: hidden;
            min-height: 520px;
            padding: 34px;
            border-radius: 30px;
            background:
                radial-gradient(circle at 14% 18%, rgba(255,255,255,.24), transparent 26%),
                linear-gradient(135deg, #0f172a 0%, #1f5eff 54%, #14b8a6 100%);
            color: #fff;
            box-shadow: 0 26px 70px rgba(15, 23, 42, .20);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .auth-hero::after {
            content: "";
            position: absolute;
            right: -90px;
            bottom: -90px;
            width: 260px;
            height: 260px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .14);
        }
        .auth-logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 950;
            letter-spacing: .03em;
        }
        .auth-logo::before {
            content: "";
            width: 34px;
            height: 34px;
            border-radius: 12px;
            background: rgba(255,255,255,.95);
            box-shadow: 0 12px 26px rgba(0,0,0,.16);
        }
        .auth-title {
            margin: 0;
            font-size: clamp(34px, 4vw, 58px);
            line-height: 1;
            letter-spacing: -.045em;
        }
        .auth-copy {
            margin: 16px 0 0;
            max-width: 560px;
            color: rgba(255,255,255,.82);
            line-height: 1.8;
        }
        .auth-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            position: relative;
            z-index: 1;
        }
        .auth-meta span {
            border: 1px solid rgba(255,255,255,.24);
            background: rgba(255,255,255,.12);
            color: rgba(255,255,255,.88);
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 850;
        }
        .auth-card {
            align-self: center;
            padding: 34px;
            border-radius: 30px;
        }
        .auth-card h1 { margin: 0; letter-spacing: -.025em; }
        .auth-card .button { min-height: 46px; }
        .alert-box {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 18px;
            line-height: 1.6;
        }
        .alert-box::before {
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            flex: 0 0 auto;
            font-weight: 950;
        }
        .alert-box.error { background: var(--danger-soft); color: #991b1b; border: 1px solid #fecaca; }
        .alert-box.error::before { content: "!"; background: #fee2e2; color: #991b1b; }
        .alert-box.status { background: var(--success-soft); color: #166534; border: 1px solid #86efac; }
        .alert-box.status::before { content: "✓"; background: #dcfce7; color: #166534; }
        .alert-title { font-weight: 950; margin-bottom: 2px; }
        .empty-state {
            text-align: center;
            padding: 30px 18px;
            color: var(--muted);
        }
        .empty-state-box {
            max-width: 560px;
            margin: 0 auto;
            border: 1px dashed #cbd5e1;
            border-radius: 20px;
            padding: 26px;
            background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(248,250,252,.82));
        }
        .empty-icon {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            background: #eef2ff;
            color: var(--primary);
            font-weight: 950;
        }
        .empty-title {
            margin: 0;
            color: var(--text);
            font-size: 18px;
            font-weight: 950;
            letter-spacing: -.02em;
        }
        .empty-copy { margin: 8px auto 0; max-width: 440px; line-height: 1.75; }
        .empty-actions { margin-top: 16px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }



        /* v0.17.4 form polish */
        .form-shell {
            display: grid;
            gap: 18px;
            margin-top: 22px;
        }
        .form-section {
            border: 1px solid rgba(217, 226, 238, .95);
            border-radius: 22px;
            padding: 20px;
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.86));
            box-shadow: var(--shadow-soft);
        }
        .form-section.compact { padding: 16px; }
        .form-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .form-section-title {
            margin: 4px 0 0;
            font-size: 18px;
            font-weight: 950;
            letter-spacing: -.025em;
        }
        .form-section-copy {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
            max-width: 720px;
        }
        .field label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #344054;
            font-size: 13px;
            font-weight: 900;
        }
        .field.required label::after {
            content: "必須";
            display: inline-flex;
            align-items: center;
            height: 20px;
            padding: 0 7px;
            border-radius: 999px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 10px;
            font-weight: 950;
        }
        .field-hint {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }
        .form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #e4e7ec;
        }
        .form-actions.sticky-ish {
            position: sticky;
            bottom: 0;
            z-index: 5;
            margin: 22px -20px -20px;
            padding: 16px 20px;
            border-radius: 0 0 22px 22px;
            background: rgba(255,255,255,.86);
            backdrop-filter: blur(10px);
        }
        .form-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .form-summary-item {
            border: 1px solid #e4e7ec;
            border-radius: 16px;
            padding: 12px;
            background: rgba(255,255,255,.72);
        }
        .form-summary-item .label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .form-summary-item .value {
            margin-top: 5px;
            font-weight: 900;
            overflow-wrap: anywhere;
        }
        .form-note {
            border: 1px solid #bfdbfe;
            border-radius: 16px;
            background: #eff6ff;
            color: #1e3a8a;
            padding: 12px 14px;
            line-height: 1.7;
            font-size: 13px;
        }


        /* v0.17.5 micro polish: buttons / badges / tables */
        .button {
            min-height: 40px;
            letter-spacing: .01em;
            border: 1px solid transparent;
        }
        .button:focus-visible,
        .nav-link:focus-visible,
        a.badge:focus-visible {
            outline: none;
            box-shadow: 0 0 0 4px rgba(31, 94, 255, .16), 0 12px 26px rgba(31, 94, 255, .16);
        }
        .button:disabled,
        .button.disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .button.light {
            color: #344054;
        }
        .button.light:hover {
            border-color: #b8c7da;
            color: #101828;
        }
        .button.secondary {
            border-color: rgba(15, 23, 42, .08);
        }
        .button.small {
            min-height: 34px;
            padding: 7px 11px;
            font-size: 12px;
        }
        .actions .button,
        .form-actions .button {
            white-space: nowrap;
        }
        .badge {
            gap: 5px;
            min-height: 26px;
            border: 1px solid transparent;
            box-shadow: inset 0 -1px 0 rgba(16, 24, 40, .04);
        }
        .badge.green { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .badge.gray { background: #f2f4f7; color: #475467; border-color: #e4e7ec; }
        .badge.red { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .badge.blue { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
        .badge.amber { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .badge.purple { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
        .badge.teal { background: #ccfbf1; color: #0f766e; border-color: #99f6e4; }
        a.badge {
            text-decoration: none;
            transition: transform .12s ease, border-color .12s ease, background .12s ease;
        }
        a.badge:hover {
            transform: translateY(-1px);
            border-color: #b8c7da;
        }
        .table-wrap {
            overflow: auto;
            background: rgba(255,255,255,.94);
        }
        .table-wrap table {
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-wrap th,
        .table-wrap td {
            line-height: 1.55;
        }
        .table-wrap th {
            position: relative;
            text-transform: none;
            white-space: nowrap;
        }
        .table-wrap td {
            background-clip: padding-box;
        }
        .table-wrap tbody tr:nth-child(even) td {
            background-color: rgba(248,250,252,.45);
        }
        .table-wrap tbody tr:hover td {
            background-color: #f6faff;
        }
        .table-wrap td.tight,
        .table-wrap th.tight {
            width: 1%;
            white-space: nowrap;
        }
        .table-wrap td .actions {
            justify-content: flex-start;
        }
        .table-wrap .button.small {
            box-shadow: none;
        }
        .domain-chip,
        .score-pill,
        .filter-chip {
            box-shadow: inset 0 -1px 0 rgba(16, 24, 40, .04);
        }
        .domain-chip {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            overflow-wrap: anywhere;
        }
        .muted a {
            color: #1d4ed8;
            text-decoration-thickness: 1px;
            text-underline-offset: 3px;
        }
        .card + .card,
        .section-card + .section-card {
            margin-top: 18px;
        }
        details.help-panel[open] {
            box-shadow: var(--shadow-soft);
        }

        @media (max-width: 900px) {
            .auth-shell { grid-template-columns: 1fr; margin: 24px auto; }
            .auth-hero { min-height: 360px; }
            .auth-card { align-self: stretch; }
        }
        @media (max-width: 720px) {
            .auth-shell { width: min(100%, calc(100% - 20px)); }
            .auth-hero, .auth-card { border-radius: 20px; padding: 24px; }
        }

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
                <a class="nav-link {{ request()->routeIs('discovery.*') ? 'active' : '' }}" href="{{ route('discovery.lab') }}">
                    候補収集ラボ
                </a>
                <a class="nav-link {{ request()->routeIs('directory-sources.lab') || request()->routeIs('directory-sources.lab.*') ? 'active' : '' }}" href="{{ route('directory-sources.lab') }}">
                    名簿元収集
                </a>
                <a class="nav-link {{ request()->routeIs('directory-sources.shokokai-bulk-html*') ? 'active' : '' }}" href="{{ route('directory-sources.shokokai-bulk-html') }}">
                    商工会HTML取込
                </a>
                <a class="nav-link {{ request()->routeIs('directory-sources.shokokai-web-search*') ? 'active' : '' }}" href="{{ route('directory-sources.shokokai-web-search') }}">
                    商工会WEBサーチ
                </a>
                <a class="nav-link {{ request()->routeIs('resolver.official-sites.*') ? 'active' : '' }}" href="{{ route('resolver.official-sites.index') }}">
                    公式HP取得
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
</body>
</html>
