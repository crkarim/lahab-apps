<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{csrf_token()}}">
    <!-- Title -->
    <title>@yield('title')</title>
    <!-- Favicon -->

    @php

        $icon = \App\Model\BusinessSetting::where(['key' => 'fav_icon'])->first()->value??'';

    @endphp
    <link rel="shortcut icon" href="">
    <link rel="icon" type="image/x-icon" href="{{ asset('storage/app/public/restaurant/' . $icon ?? '') }}">
    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&amp;display=swap" rel="stylesheet">
    <!-- CSS Implementing Plugins -->
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/vendor.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/vendor/icon-set/style.css">
    {{-- country picker --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/vendor/intl-tel-input/css/intlTelInput.css') }}" />
    {{--Carousel Slider--}}
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/owl.min.css')}}">
    <!-- CSS Front Template -->
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/theme.minc619.css?v=1.0">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/style.css?v=1.0">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/upload-file_custom.css">
    @stack('css_or_js')

    <script
        src="{{asset('public/assets/admin')}}/vendor/hs-navbar-vertical-aside/hs-navbar-vertical-aside-mini-cache.js"></script>
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/toastr.css">

    {{-- ─── Brand override layer — retunes theme's coral-red to Lahab's warm orange/brown ─── --}}
    <style>
        :root {
            --lh-orange: #E67E22;
            --lh-orange-dark: #C9661A;
            --lh-orange-tint: #FFF3E6;
            --lh-brown: #6B2F1A;
            --lh-brown-dark: #4A1F10;
        }
        /* Buttons */
        .btn-primary, .btn-primary:focus {
            background-color: var(--lh-orange) !important;
            border-color: var(--lh-orange) !important;
        }
        .btn-primary:hover, .btn-primary:active, .btn-primary:not(:disabled):not(.disabled):active,
        .btn-primary:not(:disabled):not(.disabled).active {
            background-color: var(--lh-orange-dark) !important;
            border-color: var(--lh-orange-dark) !important;
        }
        .btn-outline-primary, .btn-outline-primary:focus {
            color: var(--lh-orange) !important;
            border-color: var(--lh-orange) !important;
        }
        .btn-outline-primary:hover,
        .btn-outline-primary:not(:disabled):not(.disabled):active,
        .btn-outline-primary:not(:disabled):not(.disabled).active {
            background-color: var(--lh-orange) !important;
            border-color: var(--lh-orange) !important;
            color: #fff !important;
        }
        /* Links & text-primary accents */
        a.text-primary:hover, a.text-primary:focus,
        .text-primary { color: var(--lh-orange) !important; }
        .bg-primary { background-color: var(--lh-orange) !important; }
        .border-primary { border-color: var(--lh-orange) !important; }

        /* Form control focus ring — matches theme's default focus behaviour */
        .form-control:focus,
        .custom-select:focus {
            border-color: var(--lh-orange) !important;
            box-shadow: 0 0 0 0.2rem rgba(230,126,34,0.15) !important;
        }

        /* Theme utility classes that lean on the coral accent */
        .text-c2, .text-theme { color: var(--lh-orange) !important; }
        .bg-c2 { background-color: var(--lh-orange) !important; }

        /* Pagination active */
        .page-item.active .page-link {
            background-color: var(--lh-orange) !important;
            border-color: var(--lh-orange) !important;
        }
        .page-link { color: var(--lh-orange) !important; }
        .page-link:hover { color: var(--lh-orange-dark) !important; }

        /* Nav tabs accent */
        .nav-tabs .nav-link.active, .nav-pills .nav-link.active { color: var(--lh-orange) !important; }
        .nav-tabs .nav-link.active { border-bottom-color: var(--lh-orange) !important; }

        /* Sidebar active items */
        .navbar-vertical-aside .nav-link.active,
        .navbar-vertical-aside .nav-link:hover { color: var(--lh-orange) !important; }

        /* Badges that use bg-primary */
        .badge-primary { background-color: var(--lh-orange) !important; color: #fff !important; }

        /* Progress bars */
        .progress-bar { background-color: var(--lh-orange) !important; }

        /* Custom controls (checkbox/radio checked state) */
        .custom-control-input:checked ~ .custom-control-label::before {
            background-color: var(--lh-orange) !important;
            border-color: var(--lh-orange) !important;
        }

        /* ═════════════════════════════════════════════════════════════
           Sidebar + Header brand polish
           ═════════════════════════════════════════════════════════════ */

        /* ─── Sidebar: brand the logo frame ───────────────────────── */
        .navbar-vertical-aside .navbar-brand-wrapper {
            background: linear-gradient(180deg, #FFF8F0 0%, #ffffff 100%) !important;
            border-bottom: 1px solid #f4e6d5 !important;
        }
        .navbar-vertical-aside .navbar-brand-logo,
        .navbar-vertical-aside .navbar-brand-logo-mini { max-height: 40px; }

        /* ─── Top-level nav links ─────────────────────────────────── */
        .navbar-vertical-aside .navbar-nav .nav-link {
            border-radius: 10px !important;
            margin: 2px 10px !important;
            padding: 10px 12px !important;
            font-weight: 500 !important;
            color: #4a4a52 !important;
            transition: background 120ms, color 120ms;
        }
        .navbar-vertical-aside .navbar-nav .nav-link .nav-icon {
            color: #8e8e93 !important;
            font-size: 18px; width: 22px;
            transition: color 120ms;
        }
        .navbar-vertical-aside .navbar-nav .nav-link:hover {
            background: var(--lh-orange-tint) !important;
            color: var(--lh-brown) !important;
        }
        .navbar-vertical-aside .navbar-nav .nav-link:hover .nav-icon {
            color: var(--lh-orange) !important;
        }

        /* Active top-level item — orange-tint bg + left edge accent + ink-brown text */
        .navbar-vertical-aside .navbar-nav > li.active > .nav-link,
        .navbar-vertical-aside .navbar-nav > li.show > .nav-link,
        .navbar-vertical-aside .navbar-nav > li > .nav-link.active {
            background: var(--lh-orange-tint) !important;
            color: var(--lh-brown) !important;
            font-weight: 600 !important;
            position: relative;
        }
        .navbar-vertical-aside .navbar-nav > li.active > .nav-link .nav-icon,
        .navbar-vertical-aside .navbar-nav > li.show > .nav-link .nav-icon,
        .navbar-vertical-aside .navbar-nav > li > .nav-link.active .nav-icon {
            color: var(--lh-orange) !important;
        }
        .navbar-vertical-aside .navbar-nav > li.active > .nav-link::before,
        .navbar-vertical-aside .navbar-nav > li.show > .nav-link::before,
        .navbar-vertical-aside .navbar-nav > li > .nav-link.active::before {
            content: "";
            position: absolute; left: -4px; top: 8px; bottom: 8px;
            width: 3px; border-radius: 2px;
            background: var(--lh-orange);
            box-shadow: 0 0 6px rgba(230,126,34,0.4);
        }

        /* ─── Submenu (nav-sub) items ─────────────────────────────── */
        .navbar-vertical-aside .nav-sub .nav-link {
            margin: 1px 16px 1px 32px !important;
            padding: 8px 12px !important;
            font-size: 13px !important;
            color: #6a6a70 !important;
        }
        .navbar-vertical-aside .nav-sub .nav-indicator-icon.tio-circle {
            font-size: 6px !important;
            color: #c9cbce !important;
            transition: color 120ms;
        }
        .navbar-vertical-aside .nav-sub .nav-link:hover {
            background: #fcf5ed !important; color: var(--lh-brown) !important;
        }
        .navbar-vertical-aside .nav-sub .nav-link:hover .nav-indicator-icon {
            color: var(--lh-orange) !important;
        }
        .navbar-vertical-aside .nav-sub .nav-item.active > .nav-link {
            background: var(--lh-orange-tint) !important;
            color: var(--lh-brown) !important;
            font-weight: 600 !important;
        }
        .navbar-vertical-aside .nav-sub .nav-item.active > .nav-link .nav-indicator-icon {
            color: var(--lh-orange) !important;
            font-size: 8px !important;
        }

        /* ─── Badge pills in sidebar (Running Tables / In-Restaurant counts) ─ */
        .navbar-vertical-aside .badge-soft-success,
        .navbar-vertical-aside .badge-soft-info,
        .navbar-vertical-aside .badge-soft-primary {
            background: var(--lh-orange-tint) !important;
            color: var(--lh-brown) !important;
            font-weight: 600 !important;
            border: 1px solid rgba(230,126,34,0.2);
        }
        .sidebar--badge-container {
            display: inline-flex; align-items: center; gap: 6px;
        }

        /* ─── Collapse-toggle button in the brand area ────────────── */
        .navbar-vertical-aside .navbar-vertical-aside-toggle {
            color: var(--lh-brown) !important;
        }
        .navbar-vertical-aside .navbar-vertical-aside-toggle:hover {
            background: var(--lh-orange-tint) !important;
            color: var(--lh-orange-dark) !important;
        }

        /* ═════════════════════════════════════════════════════════════
           HEADER (top navbar)
           ═════════════════════════════════════════════════════════════ */

        /* Notification dot (messages / cart) — brand orange */
        #header .btn-status,
        #header .btn-status-c1 {
            background: var(--lh-orange) !important;
            color: #fff !important;
            box-shadow: 0 0 0 2px #fff, 0 2px 6px rgba(230,126,34,0.4);
            font-weight: 700;
        }

        /* Header icon buttons — warm hover */
        #header .btn-ghost-secondary {
            color: #6a6a70 !important;
            transition: background 120ms, color 120ms;
        }
        #header .btn-ghost-secondary:hover {
            background: var(--lh-orange-tint) !important;
            color: var(--lh-orange) !important;
        }

        /* Language dropdown topbar-link */
        #header .topbar-link {
            color: var(--lh-brown) !important;
            font-weight: 600 !important;
        }
        #header .topbar-link:hover { color: var(--lh-orange) !important; }

        /* Account chip — name/role on the right */
        #header .navbar-dropdown-account-wrapper .card-title {
            color: var(--lh-ink); font-weight: 600;
        }
        #header .navbar-dropdown-account-wrapper .card-text {
            color: var(--lh-muted);
        }
        #header .avatar-status-success {
            background: #16a34a !important;
            box-shadow: 0 0 0 2px #fff;
        }

        /* Account dropdown hover accent */
        #accountNavbarDropdown .dropdown-item:hover {
            background: var(--lh-orange-tint) !important;
            color: var(--lh-brown) !important;
        }

        /* Main header surface: tiny shadow for depth */
        #header.navbar-fixed {
            background: rgba(255,255,255,0.96) !important;
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 1px 0 #eceef0, 0 2px 8px rgba(107,47,26,0.03);
        }

        /* ═════════════════════════════════════════════════════════════
           Motion polish — re-enabled with ONLY rules that don't risk breaking
           Bootstrap modals. Anything that applies `transform` or `filter` to
           an ancestor of a `position: fixed` element is intentionally OUT,
           because it creates a new containing block and orphans the backdrop.

           Safe rules below — they either animate opacity, apply to leaf
           elements (badges, tr, buttons, icons), or are utility classes.
           ═════════════════════════════════════════════════════════════ */

        /* Opacity-only: NEVER animate transform on .content/.page-header/main. */
        @keyframes lh-fade-in { from { opacity: 0; } to { opacity: 1; } }
        @keyframes lh-badge-pulse {
            0%   { transform: scale(0.85); opacity: 0; }
            60%  { transform: scale(1.06); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes lh-status-dot {
            0%, 100% { box-shadow: 0 0 0 0 rgba(230,126,34,0.45); }
            50%      { box-shadow: 0 0 0 6px rgba(230,126,34,0); }
        }
        @keyframes lh-shimmer {
            0%   { background-position: -600px 0; }
            100% { background-position: 600px 0; }
        }

        /* Page-enter fade intentionally removed — even opacity-only animations on
           .content appeared to interact with Bootstrap 4 modal backdrop creation
           in Chromium (symptom: POS modals freezing). Status-badge + notification
           pulses + row hover still provide enough liveness. If we ever re-add a
           page-enter effect, apply it to a NEW top-level wrapper, not to .content
           which is also a modal ancestor. */

        /* Status badges — leaf elements, not modal ancestors → safe. */
        .badge-soft-success, .badge-soft-warning, .badge-soft-danger,
        .badge-soft-info, .badge-soft-primary, .badge-soft-dark {
            animation: lh-badge-pulse 280ms cubic-bezier(.2,.9,.3,1.2) both;
        }

        /* Header notification dot — leaf element → safe. */
        #header .btn-status,
        #header .btn-status-c1 {
            animation: lh-status-dot 1.6s ease-out infinite;
        }

        /* Table rows — `tr` is never a modal ancestor. Opt-in via `.lh-hover-rows`
           plus automatic on `.datatable`. POS cart / thermal receipt untouched. */
        .datatable tbody tr,
        .lh-hover-rows tbody tr {
            transition: background 160ms ease, transform 160ms ease, box-shadow 160ms ease;
        }
        .datatable tbody tr:hover,
        .lh-hover-rows tbody tr:hover {
            background: #fcf5ed !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px -8px rgba(107,47,26,0.18);
        }

        /* Skeleton shimmer helper — utility class, applied to AJAX targets. */
        .lh-skeleton {
            position: relative;
            color: transparent !important;
            background: linear-gradient(90deg, #f4f4f6 25%, #fafafa 50%, #f4f4f6 75%);
            background-size: 1200px 100%;
            background-repeat: no-repeat;
            animation: lh-shimmer 1.2s linear infinite;
            border-radius: 8px;
            pointer-events: none; user-select: none;
        }
        .lh-skeleton * { opacity: 0 !important; }

        /* Button press — leaf elements → safe. */
        .btn-primary:active,
        .pos-ix .order-place-btn:active,
        .lh-btn-primary:active {
            transition: transform 80ms;
            transform: translateY(1px) scale(0.995);
        }

        /* Reduced motion — honor the OS preference. */
        @media (prefers-reduced-motion: reduce) {
            .content, main.main > .content, .page-header,
            .badge-soft-success, .badge-soft-warning, .badge-soft-danger,
            .badge-soft-info, .badge-soft-primary, .badge-soft-dark,
            #header .btn-status, #header .btn-status-c1,
            .lh-skeleton {
                animation: none !important;
            }
            .datatable tbody tr:hover,
            .lh-hover-rows tbody tr:hover {
                transform: none !important;
            }
        }

        /* ═════════════════════════════════════════════════════════════
           Header quick-action buttons (Active Orders + New Sale).
           Always-visible top-right entry points so the operator never
           has to find the sidebar in the middle of a rush.
           ═════════════════════════════════════════════════════════════ */
        .lh-header-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            line-height: 1;
            text-decoration: none !important;
            transition: background 120ms, transform 120ms, box-shadow 120ms;
        }
        .lh-header-action i { font-size: 16px; line-height: 1; }
        .lh-header-action:hover { transform: translateY(-1px); }

        /* New Sale — solid brand orange, primary CTA. */
        .lh-header-new-sale {
            background: var(--lh-orange);
            color: #fff !important;
            box-shadow: 0 2px 6px rgba(230, 126, 34, 0.25);
        }
        .lh-header-new-sale:hover {
            background: var(--lh-orange-dark);
            color: #fff !important;
            box-shadow: 0 4px 10px rgba(230, 126, 34, 0.35);
        }

        /* Active Orders — calm white pill at idle, gets dressed up with
           coloured badges and a pulsing red shadow when there's work
           waiting. The .lh-needs-action class is added server-side when
           there are pending/confirmed orders that haven't been sent to
           the kitchen yet. */
        .lh-header-active-orders {
            background: #fff;
            color: #2d2d33 !important;
            border: 1px solid #e5e5ea;
        }
        .lh-header-active-orders:hover {
            background: #f9f9fb;
            color: #2d2d33 !important;
        }
        .lh-header-active-orders.lh-needs-action {
            background: linear-gradient(135deg, #ffefef 0%, #ffe1e1 100%);
            border-color: #f5b9b9;
            color: #b22020 !important;
            animation: lh-header-urgent 1.6s ease-in-out infinite;
        }
        .lh-header-active-orders.lh-needs-action i { color: #dc3545; }

        @keyframes lh-header-urgent {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.55); }
            50%      { box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); }
        }

        /* Badges nested inside the Active Orders button. Two flavours:
           urgent (red, pending/confirmed) and in-flight (orange, cooking
           or done). Both render side-by-side so the operator gets the
           full picture at a glance. */
        .lh-header-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 7px;
            border-radius: 11px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
        }
        .lh-header-badge-urgent {
            background: #dc3545;
            color: #fff;
            animation: lh-badge-pop 1.2s ease-in-out infinite;
        }
        .lh-header-badge-flight {
            background: var(--lh-orange);
            color: #fff;
        }
        .lh-header-badge-idle {
            background: #f0f0f4;
            color: #8a8a92;
        }

        @keyframes lh-badge-pop {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.12); }
        }

        /* Hide the long label on tighter widths so the button still fits
           between the logo and the search box. Icon + badges still tell
           the story. */
        @media (max-width: 1100px) {
            .lh-header-label { display: none; }
            .lh-header-action { padding: 0 10px; gap: 6px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .lh-header-active-orders.lh-needs-action,
            .lh-header-badge-urgent {
                animation: none !important;
            }
        }
    </style>
</head>

<body class="footer-offset">
    <div class="direction-toggle">
        <i class="tio-settings"></i>
        <span></span>
    </div>

{{--loader--}}
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div id="loading" style="display: none;">
                <div style="position: fixed;z-index: 9999; left: 40%;top: 37% ;width: 100%">
                    <img width="200" src="{{asset('public/assets/admin/img/loader.gif')}}">
                </div>
            </div>
        </div>
    </div>
</div>
{{--loader--}}

<!-- Builder -->
@include('layouts.admin.partials._front-settings')
<!-- End Builder -->

<!-- JS Preview mode only -->
@include('layouts.admin.partials._header')
@include('layouts.admin.partials._sidebar')
<!-- END ONLY DEV -->

<main id="content" role="main" class="main pointer-event">
    <!-- Content -->
    @yield('content')
    <!-- End Content -->

    @include('layouts.admin.partials._confirmation-modal')
    @include('layouts.admin.partials._command-palette')
    <!-- Footer -->
    @include('layouts.admin.partials._footer')
    <!-- End Footer -->

    <div class="modal fade" id="popup-modal">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <center>
                                <h2 style="color: rgba(96,96,96,0.68)">
                                    <i class="tio-shopping-cart-outlined"></i> {{ translate('You have new order, Check Please.') }}
                                </h2>
                                <hr>
                                <button onclick="check_order()" class="btn btn-primary">{{ translate('Ok, let me check') }}</button>
                            </center>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="toggle-status-modal">
        <div class="modal-dialog status-warning-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div class="max-349 mx-auto mb-20">
                        <div>
                            <div class="text-center">
                                <img id="toggle-status-image" alt="" class="mb-20">
                                <h5 class="modal-title" id="toggle-status-title"></h5>
                            </div>
                            <div class="text-center" id="toggle-status-message">
                            </div>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button type="button" id="toggle-status-ok-button" class="btn btn-primary min-w-120" data-dismiss="modal" onclick="confirmStatusToggle()">{{translate('Ok')}}</button>
                            <button id="reset_btn" type="reset" class="btn btn-secondary min-w-120" data-dismiss="modal">
                                {{translate("Cancel")}}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

     <!--- Global Image -->
    <div id="imageModal" class="imageModal modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header justify-content-end gap-3 border-0 p-2">
                    <button type="button" class="modal_img-btn border-0 btn-circle rounded-circle bg-section2 shadow-none fs-8 m-0"
                            data-dismiss="modal" aria-label="Close">
                            <i class="tio-clear"></i>
                    </button>
                </div>
                <div class="modal-body text-center p-3 pt-0">
                    <div class="imageModal_img_wrapper">
                        <img src="" class="img-fluid imageModal_img" alt="{{ translate('Preview_Image') }}">
                        <div class="imageModal_btn_wrapper">
                            <a href="javascript:" class="btn icon-btn download_btn" title="{{ translate('Download') }}" download>
                                <i class="tio-arrow-large-downward"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>
<!-- ========== END MAIN CONTENT ========== -->

<!-- ========== END SECONDARY CONTENTS ========== -->
<script src="{{asset('public/assets/admin')}}/js/custom.js"></script>
<!-- JS Implementing Plugins -->

@stack('script')

<!-- JS Front -->
<script src="{{asset('public/assets/admin')}}/js/vendor.min.js"></script>
<script src="{{asset('public/assets/admin')}}/js/theme.min.js"></script>
<script src="{{asset('public/assets/admin')}}/js/sweet_alert.js"></script>
<script src="{{asset('public/assets/admin')}}/js/toastr.js"></script>
<script src="{{asset('public/assets/admin/js/owl.min.js')}}"></script>
<script src="{{asset('public/assets/admin/js/firebase.min.js')}}"></script>
<script src="{{asset('public/assets/admin')}}/vendor/intl-tel-input/js/intlTelInput.js"></script>
<script src="{{asset('public/assets/admin')}}/vendor/intl-tel-input/js/utils.js"></script>
<script src="{{asset('public/assets/admin')}}/vendor/intl-tel-input/js/intlTelInout-validation.js"></script>
<script src="{{asset('public/assets/admin')}}/js/offcanvas.js"></script>
<script src="{{asset('public/assets/admin/js/file-size-type-validation.js')}}"></script>
<script src="{{asset('public/assets/admin/js/upload-file_custom.js')}}"></script>

{!! Toastr::message() !!}

@if ($errors->any())
    <script>
        @foreach($errors->all() as $error)
        toastr.error('{{$error}}', Error, {
            CloseButton: true,
            ProgressBar: true
        });
        @endforeach
    </script>
@endif
<!-- Toggle Direction Init -->
<script>
    $(document).on('ready', function(){

        $(".direction-toggle").on("click", function () {
            setDirection(localStorage.getItem("direction"));
        });

        function setDirection(direction) {
            if (direction == "rtl") {
                localStorage.setItem("direction", "ltr");
                $("html").attr('dir', 'ltr');
            $(".direction-toggle").find('span').text('Toggle RTL')
            } else {
                localStorage.setItem("direction", "rtl");
                $("html").attr('dir', 'rtl');
            $(".direction-toggle").find('span').text('Toggle LTR')
            }
        }

        if (localStorage.getItem("direction") == "rtl") {
            $("html").attr('dir', "rtl");
            $(".direction-toggle").find('span').text('Toggle LTR')
        } else {
            $("html").attr('dir', "ltr");
            $(".direction-toggle").find('span').text('Toggle RTL')
        }

    })

    //Keyborad tabs
    $(function () {
        function setActive(el) {
            // Remove from all wrappers first
            $(".cmn_focus, .cmn_focus-shadow").removeClass("active");

            // Reset any reset/submit button styles
            $("button[type='reset']").css({ "color": "", "outline": "", "box-shadow": "" });
            $("button[type='submit']").css({ "background-color": "", "outline": "", "box-shadow": "" });

            // Find wrapper around current focus
            let wrapper = el.closest(".cmn_focus, .cmn_focus-shadow");
            if (wrapper.length) {
                wrapper.addClass("active");
            }

            // If element is a reset button
            if (el.is("button[type='reset']")) {
                el.css({
                    "color": "black",
                    "outline": "2px solid #e3e3e3",
                    "box-shadow": "0 0 5px rgba(0,0,0,0.5)"
                });
            }

            //If element is a submit button
            if (el.is("button[type='submit'], button[type='button']")) {
                el.css({
//                    "background-color": "#d52a13ed",
//                    "outline": "2px solid #1397d51a",
//                    "box-shadow": "0 0 5px rgba(148, 148, 148, 0.7)"
                });
            }
        }

        // On focus (tab or click)
        $(document).on("focusin", "input, button, textarea, a", function () {
            setActive($(this));
        });

        // On Tab press (extra handling for keyboard navigation)
        $(document).on("keydown", function (e) {
            if (e.key === "Tab" || e.keyCode === 9) {
                setTimeout(function () {
                    setActive($(document.activeElement));
                }, 10);
            }
        });
    });

</script>
<!-- JS Plugins Init. -->
<script>
    // INITIALIZATION OF NAVBAR VERTICAL NAVIGATION
    // =======================================================
    var sidebar = $('.js-navbar-vertical-aside').hsSideNav();

    $(document).on('ready', function () {

        // BUILDER TOGGLE INVOKER
        // =======================================================
        $('.js-navbar-vertical-aside-toggle-invoker').click(function () {
            $('.js-navbar-vertical-aside-toggle-invoker i').tooltip('hide');
        });
        // INITIALIZATION OF UNFOLD
        // =======================================================
        $('.js-hs-unfold-invoker').each(function () {
            var unfold = new HSUnfold($(this)).init();
        });






        // INITIALIZATION OF TOOLTIP IN NAVBAR VERTICAL MENU
        // =======================================================
        $('.js-nav-tooltip-link').tooltip({boundary: 'window'})

        $(".js-nav-tooltip-link").on("show.bs.tooltip", function (e) {
            if (!$("body").hasClass("navbar-vertical-aside-mini-mode")) {
                return false;
            }
        });


    });
</script>


@stack('script_2')
<audio id="myAudio">
    <source src="{{asset('public/assets/admin/sound/notification.mp3')}}" type="audio/mpeg">
</audio>

<script>
    var audio = document.getElementById("myAudio");

    function playAudio() {
        audio.play();
    }

    function pauseAudio() {
        audio.pause();
    }

    //File Upload
    $(window).on('load', function() {
        $(".upload-file__input").on("change", function () {
        if (this.files && this.files[0]) {
            let reader = new FileReader();
            let img = $(this).siblings(".upload-file__img").find('img');

            reader.onload = function (e) {
            img.attr("src", e.target.result);
            };

            reader.readAsDataURL(this.files[0]);
        }
        });
    })
</script>
<script>

    function check_order() {
        location.href = '{{route('admin.orders.list',['status'=>'all'])}}';
    }

    $('.route-alert').on('click', function (){
        let route = $(this).data('route');
        let message = $(this).data('message');
        route_alert(route, message)
    });

    function route_alert(route, message) {
        Swal.fire({
            title: '{{translate("Are you sure?")}}',
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: '#E67E22',
            cancelButtonText: '{{translate("No")}}',
            confirmButtonText:'{{translate("Yes")}}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                location.href = route;
            }
        })
    }

    $('.form-alert').on('click', function (){
        let id = $(this).data('id');
        let message = $(this).data('message');
        form_alert(id, message)
    });

    function form_alert(id, message) {
        Swal.fire({
            title: '{{translate("Are you sure?")}}',
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: '#E67E22',
            cancelButtonText: '{{translate("No")}}',
            confirmButtonText: '{{translate("Yes")}}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $('#'+id).submit()
            }
        })
    }

    $('.redirect-url').change(function() {
        location.href=$(this).data('url');
    });

    $('.redirect-url-value').change(function() {
        var newPriority = $(this).val();
        var url = $(this).data('url') + newPriority;
        location.href=url;
    });
</script>

<script>
    function call_demo(){
        toastr.info('Update option is disabled for demo!', {
            CloseButton: true,
            ProgressBar: true
        });
    }

    $('.call-demo').click(function() {
        if ('{{ env('APP_MODE') }}' === 'demo') {
            call_demo();
        }
    });
</script>

{{-- Internet Status Check --}}
<script>
    @if(env('APP_MODE')=='live')
    //Internet Status Check
    window.addEventListener('online', function() {
        toastr.success('{{translate('Became online')}}');
    });
    window.addEventListener('offline', function() {
        toastr.error('{{translate('Became offline')}}');
    });

    //Internet Status Check (after any event)
    document.body.addEventListener("click", function(event) {
        if(window.navigator.onLine === false) {
            toastr.error('{{translate('You are in offline')}}');
            event.preventDefault();
        }
    }, false);
    @endif


</script>

<!-- IE Support -->
<script>
    if (/MSIE \d|Trident.*rv:/.test(navigator.userAgent)) document.write('<script src="{{asset('public/assets/admin')}}/vendor/babel-polyfill/polyfill.min.js"><\/script>');
</script>
<script>

    $(".status-change").change(function() {
        var value = $(this).val();
        let url = $(this).data('url');
        status_change(this, url);
    });

    function status_change(t, url) {
        let checked = $(t).prop("checked");
        let status = checked === true ? 1 : 0;

        Swal.fire({
            title: 'Are you sure?',
            text: 'Want to change status',
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#E67E22',
            cancelButtonColor: 'default',
            cancelButtonText: '{{translate("No")}}',
            confirmButtonText: '{{translate("Yes")}}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });
                $.ajax({
                    url: url,
                    data: {
                        status: status
                    },
                    success: function (data, status) {
                        toastr.success("{{translate('Status changed successfully')}}");
                    },
                    error: function (data) {
                        toastr.error("{{translate('Status changed failed')}}");
                    }
                });
            }
            else if (result.dismiss) {
                if (status == 1) {
                    $(t).prop('checked', false);
                } else if (status == 0) {
                    $(t).prop('checked', true);
                }
                toastr.info("{{translate("Status has not changed")}}");
            }
        });
    }

</script>

<script>
    let initialImages = [];
    $(window).on('load', function() {
        $("form").find('img').each(function (index, value) {
            initialImages.push(value.src);
        })
    })

    $(document).ready(function() {
        $('form').on('reset', function(e) {
            $("form").find('img').each(function (index, value) {
                $(value).attr('src', initialImages[index]);
            })
            $('.js-select2-custom').val(null).trigger('change');

        });
    });
</script>

<script>
    'use strict';
    $(document).on('ready', function () {
        $('.js-select2-custom').each(function () {
            let $select = $(this);
            let isInsideOffcanvas = $select.closest(".offcanvas").length > 0;
            let isInsideModal = $select.closest(".modal").length > 0;
            $.HSCore.components.HSSelect2.init($select, {
                dropdownParent: isInsideOffcanvas ?
                    $select.closest(".offcanvas") :
                    isInsideModal ?
                    $select.closest(".modal") :
                    null,
            });
        });
    });
</script>

<script>
        $(document).on('ready', function () {
            // INITIALIZATION OF SHOW PASSWORD
            // =======================================================
            $('.js-toggle-password').each(function () {
                new HSTogglePassword(this).init()
            });

            // INITIALIZATION OF FORM VALIDATION
            // =======================================================
            $('.js-validate').each(function () {
                $.HSCore.components.HSValidation.init($(this));
            });
        });
    </script>

<script>
    $('[data-toggle="tooltip"]').parent('label').addClass('label-has-tooltip')
</script>

<script>
        $('.blinkings').on('mouseover', ()=> $('.blinkings').removeClass('active'))
        $('.blinkings').addClass('open-shadow')
        setTimeout(() => {
            $('.blinkings').removeClass('active')
        }, 10000);
        setTimeout(() => {
            $('.blinkings').removeClass('open-shadow')
        }, 5000);
    </script>
<script>
        $(function(){
            // Guard: if .single-item-slider isn't on the page, or if owlCarousel isn't
            // registered on $.fn (e.g., a page reloaded jQuery and wiped the plugin),
            // bail silently. An uncaught throw here was breaking modal event wiring
            // downstream (modals would open but no handlers bound → couldn't close).
            var owl = $('.single-item-slider');
            if (!owl.length || typeof owl.owlCarousel !== 'function') return;

            owl.owlCarousel({
                autoplay: false,
                items:1,
                onInitialized  : counter,
                onTranslated : counter,
                autoHeight: true,
                dots: true,
            });

            function counter(event) {
                var items = event.item.count;
                var item  = event.item.index + 1;
                if (item > items) { item = item - items; }
                $('.slide-counter').html(+item+"/"+items);
            }
        });
    </script>

    <script>

        function toogleStatusModal(e, toggle_id, on_image, off_image, on_title, off_title, on_message, off_message) {
            // console.log($('#'+toggle_id).is(':checked'));
            e.preventDefault();
            if ($('#'+toggle_id).is(':checked')) {
                $('#toggle-status-title').empty().append(on_title);
                $('#toggle-status-message').empty().append(on_message);
                $('#toggle-status-image').attr('src', "{{asset('/public/assets/admin/img/modal')}}/"+on_image);
                $('#toggle-status-ok-button').attr('toggle-ok-button', toggle_id);
            } else {
                $('#toggle-status-title').empty().append(off_title);
                $('#toggle-status-message').empty().append(off_message);
                $('#toggle-status-image').attr('src', "{{asset('/public/assets/admin/img/modal')}}/"+off_image);
                $('#toggle-status-ok-button').attr('toggle-ok-button', toggle_id);
            }
            $('#toggle-status-modal').modal('show');
        }

        function confirmStatusToggle() {

            var toggle_id = $('#toggle-status-ok-button').attr('toggle-ok-button');
            if ($('#'+toggle_id).is(':checked')) {
                $('#'+toggle_id).prop('checked', false);
                $('#'+toggle_id).val(0);
            } else {
                $('#'+toggle_id).prop('checked', true);
                $('#'+toggle_id).val(1);
            }
            // console.log($('#'+toggle_id+'_form'));
            console.log(toggle_id);
            $('#'+toggle_id+'_form').submit();

        }

        function checkMailElement(id) {
            console.log(id);
            if ($('.'+id).is(':checked')) {
                $('#'+id).show();
            } else {
                $('#'+id).hide();
            }
        }

        function change_mail_route(value) {
            if(value == 'user'){
                var url= '{{url('/')}}/admin/business-settings/email-setup/'+value+'/new-order';
            }else if(value == 'dm'){
                var url= '{{url('/')}}/admin/business-settings/email-setup/'+value+'/registration';
            }
            location.href = url;
        }


        function checkedFunc() {
            $('.switch--custom-label .toggle-switch-input').each( function() {
                if(this.checked) {
                    $(this).closest('.switch--custom-label').addClass('checked')
                }else {
                    $(this).closest('.switch--custom-label').removeClass('checked')
                }
            })
        }
        checkedFunc()
        $('.switch--custom-label .toggle-switch-input').on('change', checkedFunc)

    </script>

    <script>
        @php
            $admin_order_notification = \App\CentralLogics\Helpers::get_business_settings('admin_order_notification');
        @endphp
        @php
            $admin_order_notification_type = \App\CentralLogics\Helpers::get_business_settings('admin_order_notification_type');
        @endphp
        @if(\App\CentralLogics\Helpers::module_permission_check('order_management') && $admin_order_notification)

            @if($admin_order_notification_type == 'manual')
                console.log('manual')
                setInterval(function () {
                    $.get({
                        url: '{{route('admin.get-restaurant-data')}}',
                        dataType: 'json',
                        success: function (response) {
                            let data = response.data;
                            new_order_type = data.type;
                            console.log(data)
                            if (data.new_order > 0) {
                                playAudio();
                                $('#popup-modal').appendTo("body").modal('show');
                            }
                        },
                    });
                }, 10000);
            @endif

            @if($admin_order_notification_type == 'firebase')
                @php
                    $fcm_credentials = \App\CentralLogics\Helpers::get_business_settings('fcm_credentials');
                @endphp
                var firebaseConfig = {
                    apiKey: "{{isset($fcm_credentials['apiKey']) ? $fcm_credentials['apiKey'] : ''}}",
                    authDomain: "{{isset($fcm_credentials['authDomain']) ? $fcm_credentials['authDomain'] : ''}}",
                    projectId: "{{isset($fcm_credentials['projectId']) ? $fcm_credentials['projectId'] : ''}}",
                    storageBucket: "{{isset($fcm_credentials['storageBucket']) ? $fcm_credentials['storageBucket'] : ''}}",
                    messagingSenderId: "{{isset($fcm_credentials['messagingSenderId']) ? $fcm_credentials['messagingSenderId'] : ''}}",
                    appId: "{{isset($fcm_credentials['appId']) ? $fcm_credentials['appId'] : ''}}",
                    measurementId: "{{isset($fcm_credentials['measurementId']) ? $fcm_credentials['measurementId'] : ''}}"
                };


                firebase.initializeApp(firebaseConfig);
                const messaging = firebase.messaging();

                function startFCM() {
                    messaging
                        .requestPermission()
                        .then(function() {
                            return messaging.getToken();
                        })
                        .then(function(token) {
                            subscribeTokenToBackend(token, 'admin_message');
                        }).catch(function(error) {
                            console.error('Error getting permission or token:', error);
                    });
                }

                function subscribeTokenToBackend(token, topic) {
                    fetch('{{url('/')}}/subscribeToTopic', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ token: token, topic: topic })
                    }).then(response => {
                        if (response.status < 200 || response.status >= 400) {
                            return response.text().then(text => {
                                throw new Error(`Error subscribing to topic: ${response.status} - ${text}`);
                            });
                        }
                        console.log(`Subscribed to "${topic}"`);
                    }).catch(error => {
                        console.error('Subscription error:', error);
                    });
                }

                messaging.onMessage(function(payload) {
                    console.log(payload.data);
                    if(payload.data.order_id && payload.data.type == "order_request"){
                        playAudio();
                        $('#popup-modal').appendTo("body").modal('show');
                    }
                });

                startFCM();
            @endif
        @endif

    </script>

    <script>
        $(document).ready(function() {
        // --- Changing svg color ---
            $("img.svg").each(function() {
                var $img = jQuery(this);
                var imgID = $img.attr("id");
                var imgClass = $img.attr("class");
                var imgURL = $img.attr("src");

                jQuery.get(
                    imgURL,
                    function(data) {
                        var $svg = jQuery(data).find("svg");

                        if (typeof imgID !== "undefined") {
                            $svg = $svg.attr("id", imgID);
                        }
                        if (typeof imgClass !== "undefined") {
                            $svg = $svg.attr("class", imgClass + " replaced-svg");
                        }

                        $svg = $svg.removeAttr("xmlns:a");

                        if (
                            !$svg.attr("viewBox") &&
                            $svg.attr("height") &&
                            $svg.attr("width")
                        ) {
                            $svg.attr(
                                "viewBox",
                                "0 0 " + $svg.attr("height") + " " + $svg.attr("width")
                            );
                        }
                        $img.replaceWith($svg);
                    },
                    "xml"
                );
            });
        });

    </script>


</body>
</html>
