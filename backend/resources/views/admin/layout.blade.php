<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <meta name="theme-color" content="#0B1020">
    <title>@yield('title', 'Control Center') · Aish POS</title>
    <style>{!! file_get_contents(resource_path('css/aish-tokens.css')) !!}</style>
    <style>
        :root { --shadow: 0 12px 32px rgba(11, 16, 32, .08); --sidebar-w: 248px; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; max-width: 100%; overflow-x: hidden; }
        body { font-family: var(--aish-font); color: var(--aish-text-primary); background: var(--aish-bg-default); }
        a { color: var(--aish-action-primary); }
        :focus-visible { outline: 3px solid rgba(37, 99, 235, .45); outline-offset: 2px; }
        .skip {
            position: absolute; left: -999px; top: 8px; z-index: 100;
            background: #fff; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--aish-border);
        }
        .skip:focus { left: 8px; }

        .shell { display: grid; grid-template-columns: var(--sidebar-w) 1fr; min-height: 100vh; }
        .sidebar {
            background: var(--aish-brand-dark); color: var(--aish-text-on-dark);
            padding: var(--aish-space-lg); display: flex; flex-direction: column; gap: var(--aish-space-lg);
        }
        .brand { display: flex; align-items: center; gap: var(--aish-space-md); }
        .brand-mark {
            width: 38px; height: 38px; border-radius: 10px;
            background: linear-gradient(135deg, var(--aish-brand-secondary), var(--aish-brand-support));
            display: grid; place-items: center; color: #fff; font-weight: 800;
        }
        .brand-title { font-weight: 800; font-size: 15px; }
        .brand-sub { color: var(--aish-text-on-dark-muted); font-size: 12px; }
        nav.side-nav { display: flex; flex-direction: column; gap: 4px; }
        nav.side-nav a {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px;
            color: var(--aish-text-on-dark-muted); text-decoration: none; font-weight: 600; font-size: 14px;
        }
        nav.side-nav a:hover { background: rgba(255, 255, 255, .06); color: #fff; }
        nav.side-nav a[aria-current="page"] { background: rgba(255, 255, 255, .12); color: #fff; }

        .main { display: flex; flex-direction: column; min-width: 0; }
        .topbar {
            display: flex; align-items: center; gap: var(--aish-space-md);
            padding: var(--aish-space-md) var(--aish-space-xl);
            background: var(--aish-surface); border-bottom: 1px solid var(--aish-border);
            position: sticky; top: 0; z-index: 20;
        }
        .topbar .spacer { flex: 1; }
        .topbar .who { font-size: 13px; color: var(--aish-text-secondary); }
        .menu-toggle {
            display: none; border: 1px solid var(--aish-border); background: #fff; border-radius: 10px;
            width: 40px; height: 40px; cursor: pointer; font-size: 18px;
        }
        .btn-ghost {
            border: 1px solid var(--aish-border); background: #fff; color: var(--aish-text-primary);
            border-radius: var(--aish-radius-input); padding: 8px 14px; font-weight: 600; font-size: 13px;
            cursor: pointer; font-family: inherit;
        }
        .content { padding: var(--aish-space-xl); }
        .breadcrumb { font-size: 13px; color: var(--aish-text-secondary); margin-bottom: var(--aish-space-sm); }
        .breadcrumb a { text-decoration: none; }
        h1.page-title { font-size: 22px; margin: 0 0 var(--aish-space-lg); }

        .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: var(--aish-space-lg); }
        .card {
            background: var(--aish-surface); border: 1px solid var(--aish-border);
            border-radius: var(--aish-radius-card); padding: var(--aish-space-lg); box-shadow: var(--shadow);
        }
        .card .k { font-size: 13px; color: var(--aish-text-secondary); font-weight: 600; }
        .card .v { font-size: 28px; font-weight: 800; margin-top: 6px; }
        .card .sub { font-size: 12px; color: var(--aish-text-secondary); margin-top: 4px; }
        .unavailable { color: var(--aish-text-disabled); font-weight: 700; }
        .aish-num { font-variant-numeric: tabular-nums; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

        .panel {
            background: var(--aish-surface); border: 1px solid var(--aish-border);
            border-radius: var(--aish-radius-card); box-shadow: var(--shadow); margin-top: var(--aish-space-xl);
        }
        .panel h2 { font-size: 15px; margin: 0; padding: var(--aish-space-lg); border-bottom: 1px solid var(--aish-border-subtle); }
        .panel-body { padding: var(--aish-space-lg); }

        .table-wrap { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 12px var(--aish-space-md); border-bottom: 1px solid var(--aish-border-subtle); white-space: nowrap; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--aish-text-secondary); }
        tbody tr:hover { background: var(--aish-bg-subtle); }

        .filters { display: flex; flex-wrap: wrap; gap: var(--aish-space-md); align-items: end; margin-bottom: var(--aish-space-lg); }
        .filters label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--aish-text-secondary); }
        .filters input, .filters select {
            height: 42px; border: 1px solid var(--aish-border); border-radius: var(--aish-radius-input);
            padding: 0 12px; font-family: inherit; font-size: 14px; background: #fff;
        }
        .empty { padding: var(--aish-space-2xl); text-align: center; color: var(--aish-text-secondary); }
        .pager { display: flex; gap: 8px; margin-top: var(--aish-space-lg); flex-wrap: wrap; }
        .pager a, .pager span {
            padding: 8px 12px; border: 1px solid var(--aish-border); border-radius: 8px;
            text-decoration: none; font-size: 13px; color: var(--aish-text-primary);
        }
        .pager .current { background: var(--aish-action-primary); color: #fff; border-color: var(--aish-action-primary); }
        .kv { display: grid; grid-template-columns: 200px 1fr; gap: 8px 16px; font-size: 14px; }
        .kv dt { color: var(--aish-text-secondary); font-weight: 600; }
        .kv dd { margin: 0; }

        @media (max-width: 860px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar {
                position: fixed; inset: 0 auto 0 0; width: min(84vw, var(--sidebar-w)); z-index: 40;
                transform: translateX(-100%); transition: transform var(--aish-motion-standard, 220ms) ease;
            }
            .shell[data-nav="open"] .sidebar { transform: translateX(0); }
            .menu-toggle { display: inline-flex; align-items: center; justify-content: center; }
            .kv { grid-template-columns: 1fr; }
            .backdrop { display: none; }
            .shell[data-nav="open"] .backdrop {
                display: block; position: fixed; inset: 0; background: rgba(11, 16, 32, .4); z-index: 30;
            }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
    </style>
    @stack('styles')
</head>
<body>
    <a class="skip" href="#main">Lewati ke konten</a>
    <div class="shell" id="shell" data-nav="closed">
        <div class="backdrop" id="backdrop" aria-hidden="true"></div>
        <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true">A</span>
                <span>
                    <span class="brand-title">Aish POS</span><br>
                    <span class="brand-sub">Control Center</span>
                </span>
            </div>
            <nav class="side-nav" aria-label="Menu">
                <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a>
                <a href="{{ route('admin.tenants.index') }}" @if(request()->routeIs('admin.tenants.*')) aria-current="page" @endif>Tenant</a>
                <a href="{{ route('admin.billing') }}" @if(request()->routeIs('admin.billing') || request()->routeIs('admin.billing.*')) aria-current="page" @endif>Penagihan</a>
            </nav>
        </aside>

        <div class="main">
            <header class="topbar">
                <button type="button" class="menu-toggle" id="menu-toggle"
                        aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu navigasi">☰</button>
                <strong>@yield('title', 'Control Center')</strong>
                <span class="spacer"></span>
                <span class="who">{{ auth()->user()?->email }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Keluar</button>
                </form>
            </header>

            <main class="content" id="main" role="main">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            var shell = document.getElementById('shell');
            var toggle = document.getElementById('menu-toggle');
            var backdrop = document.getElementById('backdrop');
            function setOpen(open) {
                shell.setAttribute('data-nav', open ? 'open' : 'closed');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            if (toggle) { toggle.addEventListener('click', function () { setOpen(shell.getAttribute('data-nav') !== 'open'); }); }
            if (backdrop) { backdrop.addEventListener('click', function () { setOpen(false); }); }
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); } });
        })();
    </script>
</body>
</html>
