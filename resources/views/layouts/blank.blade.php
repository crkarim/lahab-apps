<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Lahab — Install')</title>

    <link rel="shortcut icon" href="{{asset('public/assets/installation')}}/assets/img/favicon.svg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{asset('public/assets/installation')}}/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/installation')}}/assets/css/style.css">

    <style>
        :root {
            --lh-orange: #E67E22;
            --lh-orange-dark: #C9661A;
            --lh-orange-tint: #FFF3E6;
            --lh-brown: #6B2F1A;
            --lh-brown-dark: #3E1A0C;
            --lh-ink: #1a1a1a;
            --lh-muted: #6a6a70;
            --lh-border: #ececec;
            --lh-bg: #f7f6f4;
        }

        html, body {
            margin: 0; padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--lh-ink);
            -webkit-font-smoothing: antialiased;
        }

        body {
            background:
                radial-gradient(circle at 85% 10%, rgba(230,126,34,0.22), transparent 45%),
                radial-gradient(circle at 10% 85%, rgba(230,126,34,0.14), transparent 50%),
                linear-gradient(160deg, var(--lh-brown-dark) 0%, var(--lh-brown) 55%, #2A140A 100%);
            min-height: 100vh;
        }

        /* ─── Top shell ─────────────────────────────────── */
        .install-shell {
            max-width: 880px;
            margin: 0 auto;
            padding: 40px 20px 32px;
        }

        .install-header {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 28px;
            color: #fff;
        }
        .install-header__logo {
            width: 52px; height: 52px; border-radius: 12px;
            background: rgba(255,255,255,0.95);
            display: inline-flex; align-items: center; justify-content: center;
            padding: 6px;
        }
        .install-header__logo img { width: 100%; height: 100%; object-fit: contain; }
        .install-header__brand {
            font-size: 20px; font-weight: 700; letter-spacing: 0.5px;
            line-height: 1.1;
        }
        .install-header__tag {
            font-size: 12px; letter-spacing: 1.5px; text-transform: uppercase;
            color: rgba(255,255,255,0.5); font-weight: 600;
        }
        .install-header__pill {
            margin-left: auto;
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 14px; border-radius: 999px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.14);
            font-size: 12px; font-weight: 600; letter-spacing: 0.6px;
            color: #fff;
        }
        .install-header__pill .dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--lh-orange);
            box-shadow: 0 0 10px rgba(230,126,34,0.9);
        }

        /* ─── Hero title (first section in each step) ──── */
        .install-shell > section > .text-center.text-white,
        .install-shell > section > .text-center {
            /* Some steps wrap their own heading — keep it white and roomy */
        }
        .install-shell h2 {
            color: #fff;
            font-size: 28px; font-weight: 700; letter-spacing: -0.3px;
            margin-bottom: 6px;
        }
        .install-shell h6 {
            color: rgba(255,255,255,0.72);
            font-size: 14px; font-weight: 400;
            max-width: 620px; margin: 0 auto 20px;
        }

        /* ─── Progress bar retune ───────────────────────── */
        .install-shell .progress {
            height: 6px !important; border-radius: 999px;
            background: rgba(255,255,255,0.08);
            overflow: hidden;
            max-width: 640px; margin: 0 auto;
        }
        .install-shell .progress-bar {
            background: linear-gradient(90deg, var(--lh-orange) 0%, #F5A556 100%);
            border-radius: 999px;
            box-shadow: 0 0 16px rgba(230,126,34,0.6);
            transition: width 400ms ease-out;
        }

        /* ─── Card (main install pane) ──────────────────── */
        .install-shell .card {
            border: 0 !important;
            border-radius: 18px !important;
            background: #ffffff;
            box-shadow: 0 24px 60px -20px rgba(0,0,0,0.35), 0 1px 0 rgba(255,255,255,0.04);
            overflow: hidden;
            margin-top: 26px !important;
        }
        .install-shell .card > div { padding: 40px !important; }
        @media (max-width: 575px) {
            .install-shell .card > div { padding: 24px 18px !important; }
        }

        /* ─── Step pill + heading inside card ───────────── */
        .install-shell .card h5.fw-bold.fs.text-uppercase,
        .install-shell .card h5.text-uppercase {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 5px 12px; border-radius: 999px;
            background: var(--lh-orange-tint);
            color: var(--lh-brown);
            font-size: 12px !important; font-weight: 700 !important;
            letter-spacing: 0.8px; margin: 0;
        }
        .install-shell .card h5.fw-normal {
            color: var(--lh-ink);
            font-size: 22px; font-weight: 700; letter-spacing: -0.3px;
            margin: 0;
        }

        /* ─── Info rows inside .bg-light blocks ─────────── */
        .install-shell .bg-light {
            background: var(--lh-bg) !important;
            border: 1px solid var(--lh-border);
            border-radius: 14px !important;
        }
        .install-shell .bg-light h6.text-uppercase {
            color: var(--lh-brown);
            font-size: 12px !important; letter-spacing: 0.8px;
        }

        /* ─── Forms ─────────────────────────────────────── */
        .install-shell .form-control,
        .install-shell .form-select {
            height: 46px;
            border: 1px solid var(--lh-border);
            border-radius: 10px;
            padding: 0 14px;
            font-size: 14px; color: var(--lh-ink);
            background: #fff;
            transition: border-color 120ms, box-shadow 120ms;
        }
        .install-shell textarea.form-control { height: auto; padding: 12px 14px; }
        .install-shell .form-control:focus,
        .install-shell .form-select:focus {
            border-color: var(--lh-orange);
            box-shadow: 0 0 0 4px rgba(230,126,34,0.12);
            outline: 0;
        }
        .install-shell label {
            font-size: 13px; font-weight: 500; color: var(--lh-ink);
        }
        .install-shell .text-danger { color: #dc2626 !important; }

        /* ─── Buttons — retune theme's btn-dark / btn-primary to brand ─── */
        .install-shell .btn {
            border-radius: 10px; font-weight: 600; letter-spacing: 0.2px;
            padding: 12px 28px; font-size: 15px;
            transition: transform 100ms, box-shadow 140ms, background 120ms, border-color 120ms;
        }
        .install-shell .btn-dark,
        .install-shell .btn-primary {
            background: var(--lh-orange) !important;
            border-color: var(--lh-orange) !important;
            color: #fff !important;
            box-shadow: 0 8px 20px -6px rgba(230,126,34,0.45);
        }
        .install-shell .btn-dark:hover,
        .install-shell .btn-primary:hover {
            background: var(--lh-orange-dark) !important;
            border-color: var(--lh-orange-dark) !important;
            transform: translateY(-1px);
            box-shadow: 0 10px 24px -6px rgba(230,126,34,0.55);
        }
        .install-shell .btn-outline-secondary,
        .install-shell .btn-light {
            background: #fff; color: var(--lh-muted);
            border: 1px solid var(--lh-border);
        }
        .install-shell .btn-outline-secondary:hover,
        .install-shell .btn-light:hover {
            background: var(--lh-bg); color: var(--lh-ink);
        }

        /* ─── Inline links ──────────────────────────────── */
        .install-shell a {
            color: var(--lh-orange);
            text-decoration: none;
            font-weight: 500;
        }
        .install-shell a:hover { color: var(--lh-orange-dark); }

        /* ─── Top info paragraph on step0 ───────────────── */
        .install-shell .top-info-text {
            color: var(--lh-muted);
            font-size: 14px; line-height: 1.6;
            max-width: 640px; margin-left: auto !important; margin-right: auto !important;
        }

        /* ─── Footer ────────────────────────────────────── */
        .install-footer {
            margin-top: 28px;
            padding: 16px 4px;
            color: rgba(255,255,255,0.55);
            font-size: 12px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }
        .install-footer a { color: rgba(255,255,255,0.8); text-decoration: underline; }
        .install-footer a:hover { color: #fff; }
        .install-footer__logo img { height: 22px; opacity: 0.65; }

        /* Hide the legacy tooltip progress if it would stack oddly */
        .install-shell .progress[role="progressbar"] + * { }
    </style>
</head>

<body>

<div class="install-shell">

    <header class="install-header">
        <span class="install-header__logo">
            <img src="{{asset('public/assets/installation')}}/assets/img/favicon.svg" alt="{{ translate('logo') }}">
        </span>
        <div>
            <div class="install-header__brand">LAHAB</div>
            <div class="install-header__tag">{{ translate('Installation') }}</div>
        </div>
        <span class="install-header__pill"><span class="dot"></span> {{ translate('Setup Assistant') }}</span>
    </header>

    <section>
        @yield('content')
    </section>

    <footer class="install-footer">
        <div class="install-footer__logo">
            <img src="{{asset('public/assets/installation')}}/assets/img/logo.svg" alt="{{ translate('logo') }}">
        </div>
        <div>
            © {{ date('Y') }} Lahab · {{ translate('All rights reserved.') }}
            &nbsp;·&nbsp;
            <a href="mailto:rka.mahedi@gmail.com">{{ translate('Support') }}: Mahedi · rka.mahedi@gmail.com · 01620010875</a>
        </div>
    </footer>
</div>

<script src="{{asset('public/assets/installation')}}/assets/js/bootstrap.bundle.min.js"></script>
<script src="{{asset('public/assets/installation')}}/assets/js/script.js"></script>
{!! Toastr::message() !!}

<script>
    // Legacy password-confirmation wiring — only bind if the form is on this page.
    (function () {
        var passwordField = document.getElementById('password');
        var confirmationField = document.getElementById('confirm_password');
        if (!passwordField || !confirmationField) return;

        confirmationField.addEventListener('input', function () {
            if (confirmationField.value === '') {
                confirmationField.setCustomValidity('');
                return;
            }
            confirmationField.setCustomValidity(
                passwordField.value === confirmationField.value ? '' : 'The passwords do not match'
            );
        });
    })();
</script>

</body>
</html>
