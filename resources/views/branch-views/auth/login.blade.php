<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{translate('Branch')}} | {{translate('Login')}}</title>

    @php

        $icon = \App\Model\BusinessSetting::where(['key' => 'fav_icon'])->first()?->value??'';

    @endphp
    <link rel="icon" type="image/x-icon" href="{{ asset('storage/app/public/restaurant/' . $icon ?? '') }}">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/vendor/icon-set/style.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/toastr.css">

    <style>
        :root {
            --lh-orange: #E67E22;
            --lh-orange-dark: #C9661A;
            --lh-orange-tint: #FFF3E6;
            --lh-brown: #6B2F1A;
            --lh-brown-dark: #3E1A0C;
            --lh-ink: #1a1a1a;
            --lh-muted: #6a6a70;
            --lh-border: #e5e7eb;
            --lh-surface: #ffffff;
            --lh-bg: #f7f6f4;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0; padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--lh-ink);
            background: var(--lh-bg);
            height: 100%;
            -webkit-font-smoothing: antialiased;
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.1fr 1fr;
        }
        @media (max-width: 900px) {
            .auth-shell { grid-template-columns: 1fr; }
        }

        /* ─── Left brand panel — branch variant leans lighter/warmer ─── */
        .auth-brand {
            position: relative;
            background:
                radial-gradient(circle at 78% 18%, rgba(230,126,34,0.32), transparent 48%),
                radial-gradient(circle at 15% 85%, rgba(255,255,255,0.04), transparent 55%),
                linear-gradient(145deg, var(--lh-brown) 0%, var(--lh-brown-dark) 55%, #2A140A 100%);
            color: #fff;
            padding: 48px 56px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        @media (max-width: 900px) { .auth-brand { display: none; } }

        .auth-brand::before {
            content: ""; position: absolute; inset: 0;
            background: radial-gradient(circle at 50% 50%, rgba(230,126,34,0.06) 0%, transparent 60%);
            pointer-events: none;
        }

        .auth-brand__top {
            position: relative; z-index: 1;
            display: flex; align-items: center; gap: 12px;
            font-weight: 700; font-size: 18px; letter-spacing: 0.5px;
        }
        .auth-brand__top img {
            width: 44px; height: 44px; border-radius: 10px;
            background: rgba(255,255,255,0.92);
            padding: 4px; object-fit: contain;
        }
        .auth-brand__top .role {
            margin-left: auto; display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 999px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.14);
            font-size: 11px; letter-spacing: 1px; text-transform: uppercase;
            color: #fff; font-weight: 600;
        }
        .auth-brand__top .role i {
            width: 14px; height: 14px; border-radius: 50%;
            background: var(--lh-orange);
            box-shadow: 0 0 8px rgba(230,126,34,0.7);
        }

        .auth-brand__hero { position: relative; z-index: 1; max-width: 520px; }
        .auth-brand__eyebrow {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 999px;
            background: rgba(230,126,34,0.14);
            border: 1px solid rgba(230,126,34,0.3);
            font-size: 12px; letter-spacing: 0.8px; text-transform: uppercase;
            color: var(--lh-orange); margin-bottom: 22px; font-weight: 600;
        }
        .auth-brand__title {
            font-size: 40px; line-height: 1.15;
            font-weight: 800; margin: 0 0 18px 0;
            letter-spacing: -0.5px;
        }
        .auth-brand__title span { color: var(--lh-orange); }
        .auth-brand__sub {
            font-size: 15px; line-height: 1.6;
            color: rgba(255,255,255,0.7);
            margin: 0 0 26px 0; max-width: 440px;
        }
        .auth-brand__feats {
            display: grid; gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            max-width: 480px;
        }
        .auth-brand__feat {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            font-size: 13px; font-weight: 500;
            color: rgba(255,255,255,0.88);
        }
        .auth-brand__feat i {
            width: 28px; height: 28px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(230,126,34,0.16);
            color: var(--lh-orange);
            font-size: 14px;
        }

        .auth-brand__foot {
            position: relative; z-index: 1;
            font-size: 12px; color: rgba(255,255,255,0.45);
        }

        /* ─── Right form panel ─── */
        .auth-form-wrap {
            display: flex; align-items: center; justify-content: center;
            padding: 48px 40px; background: var(--lh-bg);
        }
        .auth-form {
            width: 100%; max-width: 440px;
            background: var(--lh-surface);
            border: 1px solid var(--lh-border);
            border-radius: 18px;
            padding: 40px;
            box-shadow: 0 1px 0 rgba(0,0,0,0.02), 0 24px 48px -24px rgba(107,47,26,0.12);
        }
        @media (max-width: 560px) {
            .auth-form-wrap { padding: 24px 16px; }
            .auth-form { padding: 28px 22px; border-radius: 14px; }
        }

        .auth-form__mobilelogo { display: none; margin: 0 auto 20px; }
        @media (max-width: 900px) {
            .auth-form__mobilelogo {
                display: flex; align-items: center; justify-content: center;
                width: 72px; height: 72px; border-radius: 16px;
                background: var(--lh-brown-dark);
            }
            .auth-form__mobilelogo img { width: 44px; height: 44px; object-fit: contain; }
        }

        .auth-form__pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 999px;
            background: var(--lh-orange-tint);
            color: var(--lh-brown);
            font-size: 11px; letter-spacing: 0.8px; text-transform: uppercase;
            font-weight: 600; margin-bottom: 14px;
        }
        .auth-form__pill i {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--lh-orange);
        }

        .auth-form h1 {
            font-size: 26px; font-weight: 700; letter-spacing: -0.3px;
            margin: 0 0 6px 0; color: var(--lh-ink);
        }
        .auth-form p.subtitle {
            font-size: 14px; color: var(--lh-muted); margin: 0 0 8px 0;
        }
        .auth-form .branch-cta {
            font-size: 13px; color: var(--lh-muted); margin: 0 0 26px 0;
        }
        .auth-form .branch-cta a {
            color: var(--lh-orange); font-weight: 600; text-decoration: none;
        }
        .auth-form .branch-cta a:hover { color: var(--lh-orange-dark); }

        .lh-field { margin-bottom: 16px; }
        .lh-field label {
            display: block;
            font-size: 13px; font-weight: 500;
            color: var(--lh-ink); margin-bottom: 6px;
        }
        .lh-input {
            width: 100%; height: 48px;
            padding: 0 14px;
            border: 1px solid var(--lh-border);
            border-radius: 10px;
            background: #fff;
            font-size: 14px; color: var(--lh-ink);
            transition: border-color 120ms, box-shadow 120ms, background 120ms;
        }
        .lh-input::placeholder { color: #a3a3aa; }
        .lh-input:focus {
            outline: 0;
            border-color: var(--lh-orange);
            box-shadow: 0 0 0 4px rgba(230,126,34,0.12);
            background: #fff;
        }

        .lh-pw { position: relative; }
        .lh-pw .lh-input { padding-right: 46px; }
        .lh-pw__toggle {
            position: absolute; right: 10px; top: 50%;
            transform: translateY(-50%);
            width: 32px; height: 32px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            color: var(--lh-muted); cursor: pointer;
            transition: background 120ms, color 120ms;
        }
        .lh-pw__toggle:hover { background: var(--lh-orange-tint); color: var(--lh-orange); }

        .lh-captcha-row {
            display: grid; grid-template-columns: 1fr auto; gap: 10px;
            align-items: center;
        }
        .lh-captcha-img {
            position: relative;
            height: 48px; padding: 6px 12px;
            background: #fafaf8; border: 1px solid var(--lh-border);
            border-radius: 10px;
            display: inline-flex; align-items: center; gap: 10px;
        }
        .lh-captcha-img img { height: 32px; object-fit: contain; }
        .lh-captcha-img .tio-refresh {
            color: var(--lh-orange); cursor: pointer; font-size: 16px;
        }

        .lh-remember {
            display: flex; align-items: center; justify-content: space-between;
            margin: 4px 0 22px 0;
        }
        .lh-checkbox {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 13px; color: var(--lh-muted); cursor: pointer;
            user-select: none;
        }
        .lh-checkbox input {
            appearance: none; -webkit-appearance: none;
            width: 18px; height: 18px;
            border: 1.5px solid #d4d4d9; border-radius: 5px;
            background: #fff; cursor: pointer;
            transition: background 120ms, border-color 120ms;
            position: relative;
        }
        .lh-checkbox input:checked {
            background: var(--lh-orange); border-color: var(--lh-orange);
        }
        .lh-checkbox input:checked::after {
            content: ""; position: absolute; left: 5px; top: 1px;
            width: 5px; height: 10px; border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .lh-btn-primary {
            width: 100%; height: 50px;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--lh-orange); color: #fff;
            border: 0; border-radius: 10px;
            font-size: 15px; font-weight: 600; letter-spacing: 0.2px;
            cursor: pointer;
            box-shadow: 0 8px 20px -6px rgba(230,126,34,0.45);
            transition: transform 100ms, box-shadow 140ms, background 120ms;
        }
        .lh-btn-primary:hover {
            background: var(--lh-orange-dark);
            box-shadow: 0 10px 24px -6px rgba(230,126,34,0.55);
            transform: translateY(-1px);
        }
        .lh-btn-primary:active { transform: translateY(0); }

        .auth-demo {
            margin-top: 26px; padding-top: 22px;
            border-top: 1px dashed var(--lh-border);
        }
        .auth-demo__title {
            font-size: 11px; letter-spacing: 0.8px; text-transform: uppercase;
            color: var(--lh-muted); font-weight: 600; margin-bottom: 10px;
        }
        .auth-demo__row {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            background: var(--lh-orange-tint);
            border: 1px solid rgba(230,126,34,0.18);
            border-radius: 10px; padding: 10px 14px;
            font-size: 13px; color: var(--lh-brown);
        }
        .auth-demo__row code {
            display: block; font-family: 'Inter', monospace;
            color: var(--lh-brown-dark);
        }
        .auth-demo__row button {
            width: 36px; height: 36px; border: 0; border-radius: 8px;
            background: var(--lh-orange); color: #fff; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .auth-demo__row button:hover { background: var(--lh-orange-dark); }

        .auth-foot {
            text-align: center; margin-top: 22px;
            font-size: 12px; color: var(--lh-muted);
        }

        /* Bootstrap utility — declared inline because this page only loads
           the icon-set + toastr stylesheets, not vendor.min.css. Without
           it the v3-fallback captcha block stays visible even when
           reCAPTCHA v3 is active. */
        .d-none { display: none !important; }
    </style>
</head>

<body>
<main class="auth-shell">
    {{-- ─── Left: branch brand panel ─── --}}
    <aside class="auth-brand">
        <div class="auth-brand__top">
            <img src="{{ $logo }}" alt="{{ translate('logo') }}">
            <span>LAHAB</span>
            <span class="role"><i></i> {{ translate('Branch') }}</span>
        </div>

        <div class="auth-brand__hero">
            <span class="auth-brand__eyebrow">{{ translate('Branch Operations') }}</span>
            <h1 class="auth-brand__title">{{ translate('Your floor.') }} <span>{{ translate('Your shift.') }}</span> {{ translate('Under your control.') }}</h1>
            <p class="auth-brand__sub">{{ translate('Take orders, serve tables, send to the kitchen, and close out the day — everything your branch team needs, one sign-in away.') }}</p>

            <div class="auth-brand__feats">
                <div class="auth-brand__feat"><i class="tio-shop"></i>{{ translate('Branch POS') }}</div>
                <div class="auth-brand__feat"><i class="tio-receipt"></i>{{ translate('Send to Kitchen') }}</div>
                <div class="auth-brand__feat"><i class="tio-group-senior"></i>{{ translate('Table Service') }}</div>
                <div class="auth-brand__feat"><i class="tio-receipt-outlined"></i>{{ translate('Daily Close-out') }}</div>
            </div>
        </div>

        <div class="auth-brand__foot">
            © {{ date('Y') }} Lahab. {{ translate('All rights reserved.') }}
        </div>
    </aside>

    {{-- ─── Right: branch sign-in form ─── --}}
    <section class="auth-form-wrap">
        <div class="auth-form">
            <div class="auth-form__mobilelogo">
                <img src="{{ $logo }}" alt="{{ translate('logo') }}">
            </div>

            <span class="auth-form__pill"><i></i> {{ translate('Branch sign in') }}</span>
            <h1>{{ translate('Welcome back') }}</h1>
            <p class="subtitle">{{ translate('Start your shift and keep service flowing.') }}</p>
            <p class="branch-cta">
                {{ translate('Platform owner?') }}
                <a href="{{route('admin.auth.login')}}">{{ translate('Admin login') }} →</a>
            </p>

            <form id="form-id" action="{{route('branch.auth.login')}}" method="post">
                @csrf

                <div class="lh-field">
                    <label for="signinSrEmail">{{ translate('Email address') }}</label>
                    <input type="email" class="lh-input" name="email" id="signinSrEmail"
                           placeholder="{{ translate('branch@restaurant.com') }}"
                           tabindex="1" required
                           data-msg="Please enter a valid email address.">
                </div>

                <div class="lh-field">
                    <label for="signupSrPassword">{{ translate('Password') }}</label>
                    <div class="lh-pw">
                        <input type="password" class="js-toggle-password lh-input"
                               name="password" id="signupSrPassword"
                               placeholder="{{ translate('8+ characters') }}" required
                               data-msg="Your password is invalid. Please try again."
                               data-hs-toggle-password-options='{
                                   "target": "#changePassTarget",
                                   "defaultClass": "tio-hidden-outlined",
                                   "showClass": "tio-visible-outlined",
                                   "classChangeTarget": "#changePassIcon"
                               }'>
                        <a href="javascript:" id="changePassTarget" class="lh-pw__toggle">
                            <i id="changePassIcon" class="tio-visible-outlined"></i>
                        </a>
                    </div>
                </div>

                @php

                    $recaptcha = \App\CentralLogics\Helpers::get_business_settings('recaptcha');

                @endphp
                @if(isset($recaptcha) && $recaptcha['status'] == 1)
                    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                    <input type="hidden" name="set_default_captcha" id="set_default_captcha_value" value="0">

                    <div class="lh-field d-none" id="reload-captcha">
                        <label>{{ translate('Verify you’re human') }}</label>
                        <div class="lh-captcha-row">
                            <input type="text" class="lh-input" name="default_captcha_value" autocomplete="off" placeholder="{{ translate('Enter captcha value') }}">
                            <div class="lh-captcha-img">
                                <a class="refresh-recaptcha d-inline-flex align-items-center gap-2" href="javascript:">
                                    <img src="{{ URL('/branch/auth/code/captcha/1') }}" class="input-field recaptcha-image" id="default_recaptcha_id">
                                    <i class="tio-refresh"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="lh-field">
                        <label>{{ translate('Verify you’re human') }}</label>
                        <div class="lh-captcha-row">
                            <input type="text" class="lh-input" name="default_captcha_value" autocomplete="off" placeholder="{{ translate('Enter captcha value') }}">
                            <div class="lh-captcha-img">
                                <a class="refresh-recaptcha d-inline-flex align-items-center gap-2" href="javascript:">
                                    <img src="{{ URL('/branch/auth/code/captcha/1') }}" class="input-field recaptcha-image" id="default_recaptcha_id">
                                    <i class="tio-refresh"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="lh-remember">
                    <label class="lh-checkbox" for="termsCheckbox">
                        <input type="checkbox" id="termsCheckbox" name="remember">
                        {{ translate('Remember me') }}
                    </label>
                </div>

                <button type="submit" class="lh-btn-primary" id="signInBtn">
                    {{ translate('Sign in') }}
                </button>
            </form>

            @if(env('APP_MODE')=='demo')
                <div class="auth-demo">
                    <div class="auth-demo__title">{{ translate('Demo credentials') }}</div>
                    <div class="auth-demo__row">
                        <div>
                            <code>mainb@mainb.com</code>
                            <code>12345678</code>
                        </div>
                        <button type="button" id="copyButton" title="{{ translate('Copy & autofill') }}">
                            <i class="tio-copy"></i>
                        </button>
                    </div>
                </div>
            @endif

            <div class="auth-foot">
                {{ translate('Powered by Lahab') }}
            </div>
        </div>
    </section>
</main>

<script src="{{asset('public/assets/admin')}}/js/vendor.min.js"></script>
<script src="{{asset('public/assets/admin')}}/js/theme.min.js"></script>
<script src="{{asset('public/assets/admin')}}/js/toastr.js"></script>
{!! Toastr::message() !!}

@if ($errors->any())
    <script>
        @foreach($errors->all() as $error)
        toastr.error('{{$error}}', Error, { CloseButton: true, ProgressBar: true });
        @endforeach
    </script>
@endif

<script>
    $(document).on('ready', function () {
        $('.js-toggle-password').each(function () {
            new HSTogglePassword(this).init();
        });
        $('.js-validate').each(function () {
            $.HSCore.components.HSValidation.init($(this));
        });
    });
</script>

@if(isset($recaptcha) && $recaptcha['status'] == 1)
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://www.google.com/recaptcha/api.js?render={{$recaptcha['site_key']}}"></script>
    <script>
        "use strict";
        $('#signInBtn').click(function (e) {
            if( $('#set_default_captcha_value').val() == 1){
                $('#form-id').submit();
                return true;
            }
            e.preventDefault();
            if (typeof grecaptcha === 'undefined') {
                toastr.error('Invalid recaptcha key provided. Please check the recaptcha configuration.');
                $('#reload-captcha').removeClass('d-none');
                $('#set_default_captcha_value').val('1');
                return;
            }
            grecaptcha.ready(function () {
                grecaptcha.execute('{{$recaptcha['site_key']}}', {action: 'submit'}).then(function (token) {
                    document.getElementById('g-recaptcha-response').value = token;
                    document.querySelector('form').submit();
                });
            });
            window.onerror = function(message) {
                var errorMessage = 'An unexpected error occurred. Please check the recaptcha configuration';
                if (message.includes('Invalid site key')) {
                    errorMessage = 'Invalid site key provided. Please check the recaptcha configuration.';
                } else if (message.includes('not loaded in api.js')) {
                    errorMessage = 'reCAPTCHA API could not be loaded. Please check the recaptcha API configuration.';
                }
                $('#reload-captcha').removeClass('d-none');
                $('#set_default_captcha_value').val('1');
                toastr.error(errorMessage);
                return true;
            };
        });
    </script>
@endif

<script type="text/javascript">
    $('.refresh-recaptcha').on('click', function() { re_captcha(); });

    function re_captcha() {
        $url = "{{ URL('/branch/auth/code/captcha') }}";
        $url = $url + "/" + Math.random();
        document.getElementById('default_recaptcha_id').src = $url;
    }
</script>

@if(env('APP_MODE')=='demo')
    <script>
        $('#copyButton').on('click', function() { copy_cred(); });

        function copy_cred() {
            $('#signinSrEmail').val('mainb@mainb.com');
            $('#signupSrPassword').val('12345678');
            toastr.success('Copied successfully!', 'Success!', {
                CloseButton: true, ProgressBar: true
            });
        }
    </script>
@endif

<script>
    if (/MSIE \d|Trident.*rv:/.test(navigator.userAgent)) document.write('<script src="{{asset('public/assets/admin')}}/vendor/babel-polyfill/polyfill.min.js"><\/script>');
</script>
</body>
</html>
