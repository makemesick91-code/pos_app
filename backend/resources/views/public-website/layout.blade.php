{{-- Sprint 21 — public website layout. Lightweight, mobile-first, secret-free.
     No external CDN, no live analytics/ad pixel. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seoTitle ?? 'Aish POS Lite' }}</title>
    <meta name="description" content="{{ $seoDescription ?? 'Aish POS Lite — POS Android SaaS untuk UMKM.' }}">
    {{-- SEO readiness placeholders (Sprint 21). No live tracking token. --}}
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="robots" content="index,follow">
    <meta property="og:title" content="{{ $seoTitle ?? 'Aish POS Lite' }}">
    <meta property="og:description" content="{{ $seoDescription ?? 'POS Android SaaS untuk UMKM.' }}">
    <meta property="og:type" content="website">
    <style>
        :root { --ink:#12203a; --muted:#5b6b86; --brand:#1f6feb; --bg:#f6f8fc; --card:#ffffff; --line:#e4eaf3; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
               color:var(--ink); background:var(--bg); line-height:1.55; }
        a { color:var(--brand); text-decoration:none; }
        .wrap { max-width:960px; margin:0 auto; padding:0 20px; }
        header.site { background:var(--card); border-bottom:1px solid var(--line); position:sticky; top:0; z-index:5; }
        header.site .wrap { display:flex; align-items:center; justify-content:space-between; height:60px; }
        .brand { font-weight:700; font-size:1.15rem; }
        nav a { margin-left:16px; color:var(--muted); font-size:.95rem; }
        .btn { display:inline-block; background:var(--brand); color:#fff; padding:12px 22px; border-radius:10px;
               font-weight:600; border:0; cursor:pointer; }
        .btn.secondary { background:#eaf1fe; color:var(--brand); }
        .hero { padding:56px 0 40px; text-align:center; }
        .hero h1 { font-size:2rem; margin:0 0 12px; }
        .hero p { color:var(--muted); font-size:1.1rem; max-width:640px; margin:0 auto 24px; }
        .grid { display:grid; gap:16px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:20px; }
        .card h3 { margin:0 0 8px; font-size:1.05rem; }
        .card p { margin:0; color:var(--muted); font-size:.95rem; }
        section { padding:32px 0; }
        section h2 { font-size:1.4rem; margin:0 0 18px; text-align:center; }
        .price { font-size:1.5rem; font-weight:700; }
        .muted { color:var(--muted); }
        .note { background:#fff7e6; border:1px solid #ffe2a8; border-radius:10px; padding:12px 16px; font-size:.9rem; color:#7a5b12; }
        form.lead { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:24px; max-width:560px; margin:0 auto; }
        form.lead label { display:block; font-size:.9rem; font-weight:600; margin:12px 0 4px; }
        form.lead input, form.lead textarea, form.lead select {
            width:100%; padding:11px 12px; border:1px solid var(--line); border-radius:9px; font:inherit; }
        form.lead .consent { display:flex; gap:8px; align-items:flex-start; margin:14px 0; font-size:.9rem; color:var(--muted); }
        form.lead .consent input { width:auto; margin-top:3px; }
        .errors { background:#fdecea; border:1px solid #f5c2c0; color:#8a1c15; border-radius:10px; padding:12px 16px; margin-bottom:14px; font-size:.9rem; }
        footer.site { border-top:1px solid var(--line); padding:28px 0; color:var(--muted); font-size:.9rem; text-align:center; }
        footer.site a { margin:0 8px; }
        .legal { max-width:720px; }
        .legal h1 { font-size:1.6rem; }
        @media (max-width:560px){ .hero h1{font-size:1.6rem;} nav a{margin-left:10px;} }
    </style>
</head>
<body>
    <header class="site">
        <div class="wrap">
            <span class="brand">Aish POS Lite</span>
            <nav>
                <a href="/">Beranda</a>
                <a href="/packages">Paket</a>
                <a href="/#interest">Hubungi</a>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site">
        <div class="wrap">
            <div>Aish POS Lite — POS Android SaaS untuk UMKM.</div>
            <div style="margin-top:8px;">
                <a href="/privacy">Privasi</a>·
                <a href="/terms">Ketentuan</a>·
                <a href="/packages">Paket</a>
            </div>
            <div class="muted" style="margin-top:8px;">Belum ada pendaftaran mandiri atau penagihan otomatis. Aktivasi melalui tim kami.</div>
        </div>
    </footer>
</body>
</html>
