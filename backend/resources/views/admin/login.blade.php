<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <meta name="theme-color" content="#0B1020">
    <title>Masuk · Aish POS Control Center</title>
    <style>{!! file_get_contents(resource_path('css/aish-tokens.css')) !!}</style>
    <style>
        :root { --shadow: 0 18px 50px rgba(11, 16, 32, .12); }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: var(--aish-font);
            color: var(--aish-text-primary);
            background:
                radial-gradient(1200px 600px at 100% -10%, rgba(109, 93, 251, .10), transparent 60%),
                var(--aish-bg-default);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--aish-space-lg);
        }
        .login-card {
            width: min(420px, 100%);
            background: var(--aish-surface);
            border: 1px solid var(--aish-border);
            border-radius: var(--aish-radius-sheet);
            box-shadow: var(--shadow);
            padding: var(--aish-space-2xl);
        }
        .brand { display: flex; align-items: center; gap: var(--aish-space-md); margin-bottom: var(--aish-space-xl); }
        .brand-mark {
            width: 44px; height: 44px; border-radius: 12px;
            background: linear-gradient(135deg, var(--aish-brand-dark), var(--aish-brand-secondary));
            display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 18px;
        }
        .brand-title { font-weight: 800; font-size: 16px; line-height: 1.2; }
        .brand-sub { color: var(--aish-text-secondary); font-size: 13px; }
        h1 { font-size: 20px; margin: 0 0 var(--aish-space-xs); }
        .lead { color: var(--aish-text-secondary); font-size: 14px; margin: 0 0 var(--aish-space-xl); }
        .field { margin-bottom: var(--aish-space-lg); }
        label { display: block; font-weight: 600; font-size: 13px; margin-bottom: var(--aish-space-xs); }
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%; height: 48px; padding: 0 var(--aish-space-md);
            border: 1px solid var(--aish-border); border-radius: var(--aish-radius-input);
            font-size: 15px; font-family: inherit; color: var(--aish-text-primary); background: #fff;
        }
        input:focus-visible, button:focus-visible, a:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .45); outline-offset: 2px;
        }
        .pw-wrap { position: relative; display: flex; }
        .pw-wrap input { padding-right: 68px; }
        .pw-toggle {
            position: absolute; right: 6px; top: 6px; height: 36px; border: 0; cursor: pointer;
            background: var(--aish-bg-subtle); color: var(--aish-action-primary);
            border-radius: 8px; padding: 0 12px; font-size: 12px; font-weight: 700; font-family: inherit;
        }
        .remember { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--aish-text-secondary); }
        .remember input { width: 18px; height: 18px; }
        .aish-btn-primary { width: 100%; }
        .form-error {
            background: var(--aish-status-danger-bg, #FBE7E7);
            border: 1px solid var(--aish-status-danger-border, #F2C9C9);
            color: var(--aish-status-danger-fg, #B91C1C);
            border-radius: var(--aish-radius-input);
            padding: var(--aish-space-md); font-size: 14px; margin-bottom: var(--aish-space-lg);
        }
        .foot { margin-top: var(--aish-space-xl); font-size: 12px; color: var(--aish-text-disabled); text-align: center; }
        button[disabled] { opacity: .7; cursor: progress; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
    </style>
</head>
<body>
    <main class="login-card" role="main">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">A</span>
            <span>
                <span class="brand-title">Aish POS</span><br>
                <span class="brand-sub">SaaS Control Center</span>
            </span>
        </div>

        <h1>Masuk Platform Admin</h1>
        <p class="lead">Portal operasi internal. Akses terbatas untuk administrator platform.</p>

        @if ($errors->any())
            <div class="form-error" role="alert" aria-live="assertive">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}" novalidate>
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="username"
                       value="{{ old('email') }}" required autofocus
                       aria-invalid="@error('email')true @else false @enderror"
                       @error('email') aria-describedby="email-error" @enderror>
            </div>

            <div class="field">
                <label for="password">Kata sandi</label>
                <div class="pw-wrap">
                    <input id="password" name="password" type="password" autocomplete="current-password"
                           required aria-describedby="pw-help">
                    <button type="button" class="pw-toggle" id="pw-toggle"
                            aria-controls="password" aria-pressed="false">Lihat</button>
                </div>
                <span id="pw-help" class="brand-sub" style="font-size:12px;">Minimal 12 karakter.</span>
            </div>

            <div class="field remember">
                <input id="remember" name="remember" type="checkbox" value="1">
                <label for="remember" style="margin:0;font-weight:500;">Ingat perangkat ini</label>
            </div>

            <button type="submit" class="aish-btn-primary" id="submit-btn" data-loading-label="Memproses…">
                Masuk
            </button>
        </form>

        <p class="foot">Aktivitas login dicatat. Jangan bagikan kredensial Anda.</p>
    </main>

    <script>
        (function () {
            var toggle = document.getElementById('pw-toggle');
            var pw = document.getElementById('password');
            if (toggle && pw) {
                toggle.addEventListener('click', function () {
                    var show = pw.type === 'password';
                    pw.type = show ? 'text' : 'password';
                    toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                    toggle.textContent = show ? 'Sembunyi' : 'Lihat';
                    pw.focus();
                });
            }
            var form = document.querySelector('form');
            var btn = document.getElementById('submit-btn');
            if (form && btn) {
                form.addEventListener('submit', function () {
                    btn.disabled = true;
                    btn.textContent = btn.getAttribute('data-loading-label');
                });
            }
        })();
    </script>
</body>
</html>
