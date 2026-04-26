@extends('layouts.admin.app')

@section('title', translate('New_Sale'))

@section('content')
    <style>
        /* ═══════════════════════════════════════════════════════════════════
           POS redesign (scope B). All rules scoped to .pos-ix (main wrapper).
           Cart partial (_cart.blade.php) uses .billing-section-wrap, which we
           also target — those are only present on this page, so no bleed.
           ═══════════════════════════════════════════════════════════════════ */

        .pos-ix-card {
            background: #fff; border-radius: 14px; padding: 18px 18px 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        /* Search */
        .pos-ix-search { margin-bottom: 14px; }
        .pos-ix-search-wrap {
            position: relative; background: #f5f6f8; border: 1px solid #eceef0;
            border-radius: 12px; display: flex; align-items: center;
            transition: border-color 120ms, background 120ms;
        }
        .pos-ix-search-wrap:focus-within { background: #fff; border-color: #E67E22; }
        .pos-ix-search-wrap i.tio-search {
            padding: 0 12px; color: #8e8e93; font-size: 18px;
        }
        .pos-ix-search-wrap input {
            flex: 1; background: transparent; border: 0; outline: 0;
            height: 48px; font-size: 15px; font-weight: 500; padding-right: 44px;
        }

        /* Category pill row */
        .pos-ix-pills {
            display: flex; gap: 8px; overflow-x: auto;
            padding: 4px 2px 12px; margin-bottom: 10px;
            scrollbar-width: thin;
        }
        .pos-ix-pills::-webkit-scrollbar { height: 4px; }
        .pos-ix-pills::-webkit-scrollbar-thumb { background: #eceef0; border-radius: 2px; }
        .pos-ix-pill {
            flex-shrink: 0; padding: 9px 16px; border-radius: 10px;
            background: #f5f6f8; border: 1px solid transparent;
            font-size: 14px; font-weight: 600; color: #444; cursor: pointer;
            min-height: 40px; transition: all 120ms ease;
        }
        .pos-ix-pill:hover { background: #eaeaef; color: #1a1a1a; }
        .pos-ix-pill.is-active {
            background: #E67E22; color: #fff; border-color: #E67E22;
            box-shadow: 0 2px 8px rgba(230,126,34,0.25);
        }

        /* Favorites hero */
        .pos-ix-favs {
            background: linear-gradient(180deg, rgba(230,126,34,0.05), transparent);
            border: 1px solid rgba(230,126,34,0.12); border-radius: 12px;
            padding: 12px 14px; margin-bottom: 14px;
        }
        .pos-ix-favs-label {
            font-size: 11px; font-weight: 700; letter-spacing: 0.8px;
            text-transform: uppercase; color: #E67E22; margin-bottom: 10px;
        }
        .pos-ix-favs-row {
            display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px;
        }
        @media (max-width: 1399px) { .pos-ix-favs-row { grid-template-columns: repeat(5, 1fr); } }
        @media (max-width: 1199px) { .pos-ix-favs-row { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 767px)  { .pos-ix-favs-row { grid-template-columns: repeat(3, 1fr); } }

        /* Product grid wrapper */
        .pos-ix-grid-wrap { min-height: 200px; }

        /* ═══════════════════════════════════════════════════════════════════
           Cart column polish — sticky bottom CTA, chip order-type, touch ±.
           Scoped to the cart column (col-lg-4) + its descendants.
           ═══════════════════════════════════════════════════════════════════ */

        /* Cart column: full-height flex so the Place Order area can pin to bottom. */
        .pos-ix .col-lg-4 > .card {
            position: sticky; top: 84px;
            max-height: calc(100vh - 100px);
            display: flex; flex-direction: column;
            border-radius: 14px !important; border: 1px solid #eceef0;
        }
        .pos-ix .billing-section-wrap {
            display: flex; flex-direction: column; flex: 1 1 auto;
            overflow: hidden;
        }
        .pos-ix .billing-section-wrap > .pos-title { flex: 0 0 auto; }
        .pos-ix .billing-section-wrap > div.p-2,
        .pos-ix .billing-section-wrap > div.p-sm-4 {
            flex: 1 1 auto; overflow-y: auto;
        }

        /* ─── Order-type radios → segmented chip group (Dine-in / Take-away / Delivery) ─── */
        .pos-ix .order_type_radio {
            background: #f3f4f6 !important; border: 0 !important;
            border-radius: 12px !important; padding: 4px !important;
            display: grid !important;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
        }
        .pos-ix .order_type_radio > label.custom-radio {
            margin: 0 !important; padding: 10px 4px !important;
            border-radius: 10px; cursor: pointer;
            text-align: center; display: block !important;
            transition: background 120ms, color 120ms, box-shadow 120ms;
            font-size: 13px; font-weight: 600; color: #555;
            min-height: 44px;
        }
        .pos-ix .order_type_radio > label.custom-radio .media-body {
            font-weight: 600; font-size: 13px;
        }
        /* Modern :has() for checked state */
        .pos-ix .order_type_radio > label.custom-radio:has(input:checked) {
            background: #E67E22; color: #fff;
            box-shadow: 0 2px 8px rgba(230,126,34,0.28);
        }
        .pos-ix .order_type_radio > label.custom-radio:has(input:checked) .media-body { color: #fff; }

        /* Kill native radio circles bleeding through chip labels (both chip groups). */
        .pos-ix .order_type_radio input[type="radio"],
        .pos-ix .option-buttons input[type="radio"] {
            position: absolute !important;
            opacity: 0 !important;
            pointer-events: none !important;
            width: 0 !important; height: 0 !important;
            margin: 0 !important; padding: 0 !important;
            clip: rect(0 0 0 0);
        }
        .pos-ix .order_type_radio > label.custom-radio .media { padding: 0 !important; }

        /* ─── Dine-in table / people inputs: wrap inside a subtle card so they feel connected ─── */
        .pos-ix #dine_in_section {
            margin-top: 12px; padding: 12px;
            background: #fff; border-radius: 10px; border: 1px solid #eceef0;
        }
        .pos-ix #dine_in_section .form-group { margin-bottom: 8px; }
        .pos-ix #dine_in_section .form-group:last-child { margin-bottom: 0; }

        /* ─── Paid By row → segmented chip group, matching Order Type ─── */
        .pos-ix .pos-paid-by { margin-top: 4px; }
        .pos-ix .pos-paid-by .pos-label {
            font-size: 11px; letter-spacing: 0.8px; text-transform: uppercase;
            color: #8e8e93; font-weight: 700; margin-bottom: 8px;
        }
        .pos-ix .option-buttons {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 4px !important;
            padding: 4px !important;
            margin: 0 !important;
            background: #f3f4f6;
            border-radius: 12px;
            list-style: none;
        }
        .pos-ix .option-buttons > li {
            margin: 0 !important; padding: 0 !important;
            display: block; list-style: none;
        }
        .pos-ix .option-buttons > li.d-none { display: none !important; }
        .pos-ix .option-buttons > li > label.btn {
            display: flex !important; align-items: center; justify-content: center;
            width: 100% !important; margin: 0 !important;
            padding: 10px 8px !important;
            min-height: 44px;
            border-radius: 10px !important;
            background: transparent !important; border: 0 !important;
            color: #555 !important;
            font-size: 13px !important; font-weight: 600 !important;
            text-align: center; cursor: pointer;
            transition: background 120ms, color 120ms, box-shadow 120ms;
        }
        .pos-ix .option-buttons > li:has(input:checked) > label.btn {
            background: #E67E22 !important;
            color: #fff !important;
            box-shadow: 0 2px 8px rgba(230,126,34,0.28);
        }

        /* ─── Paid Amount / Change section ─── */
        .pos-ix .collect-cash-section {
            margin-top: 14px; padding: 12px;
            background: #fff; border-radius: 10px; border: 1px solid #eceef0;
        }
        .pos-ix .collect-cash-section .form-group { margin-bottom: 8px; }
        .pos-ix .collect-cash-section .form-group:last-child { margin-bottom: 0; }
        .pos-ix .collect-cash-section label {
            font-size: 13px; font-weight: 500; color: #555; margin: 0;
        }
        .pos-ix .collect-cash-section input.form-control {
            height: 40px; border-radius: 8px;
            border: 1px solid #e1e3e6; font-weight: 700;
            font-size: 15px; color: #1a1a1a;
        }
        .pos-ix .collect-cash-section #amount-difference {
            background: #f8f9fa !important; border-color: #eceef0 !important;
            color: #E67E22 !important;
        }

        /* ─── Customer-wallet cards: subtle panels ─── */
        .pos-ix .customer-wallet-info-card,
        .pos-ix .wallet-remaining-card {
            margin-top: 12px !important; padding: 12px !important;
            background: #fff !important; border-radius: 10px !important;
            border: 1px solid #eceef0 !important; box-shadow: none !important;
        }

        /* ─── Totals block: tighter, cleaner typography ─── */
        .pos-ix .pos-data-table { padding: 10px 0 0 0 !important; }
        .pos-ix .pos-data-table dl.row { margin: 0; }
        .pos-ix .pos-data-table dl.row dt,
        .pos-ix .pos-data-table dl.row dd {
            padding: 4px 6px; font-size: 13px;
        }
        .pos-ix .pos-data-table dl.row dt { color: #6a6a70; font-weight: 500; }
        .pos-ix .pos-data-table dl.row dd { color: #1a1a1a; font-weight: 600; }
        .pos-ix .pos-data-table dl.row dt.pos-total-row,
        .pos-ix .pos-data-table dl.row dd.pos-total-row {
            border-top: 1px solid #eceef0; margin-top: 6px; padding-top: 10px;
            font-size: 16px; font-weight: 700; color: #1a1a1a;
        }

        /* ─── Cart item rows ─── */
        .pos-ix .pos-cart-table { border: 0; }
        .pos-ix .pos-cart-table thead { background: transparent; }
        .pos-ix .pos-cart-table thead th {
            font-size: 10px; letter-spacing: 0.6px; text-transform: uppercase;
            color: #8e8e93; font-weight: 700; padding: 8px 6px;
            border-bottom: 1px solid #eceef0 !important;
        }
        .pos-ix .pos-cart-table tbody tr {
            transition: background 120ms;
        }
        .pos-ix .pos-cart-table tbody tr:hover { background: #fafbfc; }
        .pos-ix .pos-cart-table tbody td {
            padding: 12px 6px !important; vertical-align: top;
            border-top: 1px dashed #f0f0f2 !important;
        }
        .pos-ix .pos-cart-table .avatar { width: 40px; height: 40px; border-radius: 8px; }
        .pos-ix .pos-cart-table .qty {
            width: 52px !important; height: 36px;
            text-align: center; font-weight: 600;
            border-radius: 8px; border: 1px solid #e1e3e6 !important;
        }
        /* Remove (X) button prominence on hover */
        .pos-ix .pos-cart-table .tio-delete,
        .pos-ix .pos-cart-table .tio-clear {
            color: #c9cbce; transition: color 120ms, transform 120ms;
        }
        .pos-ix .pos-cart-table tbody tr:hover .tio-delete,
        .pos-ix .pos-cart-table tbody tr:hover .tio-clear {
            color: #dc3545; transform: scale(1.12);
        }

        /* ─── Running total block ─── */
        .pos-ix .billing-section-wrap dl.row {
            margin: 0; padding: 2px 0; font-size: 13px;
        }
        .pos-ix .billing-section-wrap dl.row dt { color: #6a6a70; font-weight: 500; }
        .pos-ix .billing-section-wrap dl.row dd { color: #1a1a1a; font-weight: 600; margin: 0; }

        /* ─── Place Order CTA — big sticky brand button at the very bottom ─── */
        .pos-ix #order_place {
            flex: 0 0 auto;
            padding: 12px 16px !important;
            background: rgba(255,255,255,0.96);
            backdrop-filter: blur(6px);
            border-top: 1px solid #eceef0;
            position: sticky; bottom: 0; z-index: 5;
        }
        /* Theme ships `.pos-order-btn { position:absolute; bottom:0 }` — kills its floating
           behaviour so Cancel/Place Order sits in normal flow below the Paid Amount row. */
        .pos-ix .pos-order-btn {
            position: static !important;
            padding: 0 !important;
            background: transparent !important;
            width: auto !important;
            margin-top: 14px;
        }
        .pos-ix .pb-130px { padding-bottom: 16px !important; }
        .pos-ix #order_place .order-place-btn {
            height: 56px !important; font-size: 16px !important;
            font-weight: 700 !important; letter-spacing: 0.3px;
            border-radius: 12px !important;
            background: #E67E22 !important; border: 0 !important;
            box-shadow: 0 6px 16px rgba(230,126,34,0.32);
            transition: transform 100ms, box-shadow 100ms;
        }
        .pos-ix #order_place .order-place-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(230,126,34,0.42);
        }
        .pos-ix #order_place .order-place-btn:active { transform: translateY(0); }

        /* Cancel Order — same height/shape as Place Order, muted palette. */
        .pos-ix #order_place .cancel-order-btn {
            height: 56px !important; font-size: 16px !important;
            font-weight: 600 !important; letter-spacing: 0.3px;
            border-radius: 12px !important;
            display: inline-flex !important; align-items: center; justify-content: center;
            background: #f3f4f6 !important; color: #6a6a70 !important;
            border: 1px solid #e5e7eb !important;
            transition: background 120ms, color 120ms, border-color 120ms;
        }
        .pos-ix #order_place .cancel-order-btn:hover {
            background: #eceef0 !important; color: #1a1a1a !important;
            border-color: #d9dbdf !important;
        }

        /* ───── Tablet-first POS tuning (inherited from previous pass). ───── */

        /* Larger, more tappable product cards. Base gets a slight bump, tablet goes bigger. */
        .pos-item-wrap {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)) !important;
            gap: 16px !important;
        }
        @media (min-width: 768px) and (max-width: 1399px) {
            .pos-item-wrap { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)) !important; }
        }
        .pos-product-item {
            border: 1px solid #eceef0 !important;
            transition: transform 120ms ease, box-shadow 140ms ease, border-color 120ms ease;
        }
        .pos-product-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
            border-color: #E67E22 !important;
        }
        .pos-product-item_thumb { height: 140px !important; }
        .pos-product-item_title { font-size: 14px; font-weight: 600; }
        .pos-product-item_price { font-size: 15px !important; color: #E67E22 !important; }

        /* 44px minimum touch targets (Apple HIG) on common POS controls. */
        .pos-product-item { min-height: 44px; }
        .option-buttons label,
        .order_type_radio label { min-height: 44px; }
        .order_type_radio { padding: 6px !important; }
        .order_type_radio label {
            font-size: 14px; font-weight: 500;
            padding: 10px 12px !important; border-radius: 8px !important;
            transition: background 120ms, color 120ms;
        }
        .order_type_radio input:checked + .media,
        .order_type_radio label:has(input:checked) {
            background: #E67E22; color: #fff;
        }

        /* Cart quantity +/- buttons — enlarge for finger use. */
        .billing-section-wrap .qty-btn,
        .billing-section-wrap .quantity-action,
        .billing-section-wrap .tio-add,
        .billing-section-wrap .tio-remove {
            min-width: 36px; min-height: 36px;
        }
        .pos-cart-table td { padding-top: 10px !important; padding-bottom: 10px !important; }

        /* Search input — taller, rounder, hint-friendly. */
        #datatableSearch {
            height: 44px; font-size: 15px;
            border-radius: 10px !important;
        }

        /* Category select — taller on tablet. */
        select.category { height: 44px !important; font-size: 15px !important; }

        /* Place-order submit — prominent. */
        #order_place button[type="submit"] {
            height: 48px; font-size: 15px; font-weight: 600;
            border-radius: 10px !important;
            box-shadow: 0 4px 10px rgba(230,126,34,0.18);
        }

        /* Billing section — roomier on tablet. */
        @media (min-width: 992px) {
            .billing-section-wrap { padding-inline: 8px !important; }
        }
    </style>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div id="loading" class="d--none">
                    <div class="loading-inner">
                        <img width="200" src="{{asset('public/assets/admin/img/loader.gif')}}">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="content">
        <section class="section-content padding-y-sm bg-default">
            <div class="container-fluid pos-ix">
                <div class="row gy-3 gx-2">
                    <div class="col-lg-8">
                        <div class="pos-ix-card">

                            {{-- Search — prominent, ⌘/ hint lives here via existing JS --}}
                            <div class="pos-ix-search">
                                <form id="search-form" class="mb-0 w-100">
                                    <div class="pos-ix-search-wrap">
                                        <i class="tio-search"></i>
                                        <input id="datatableSearch" type="search"
                                               value="{{$keyword?$keyword:''}}" name="search"
                                               placeholder="{{ translate('Search any product…') }}"
                                               aria-label="Search">
                                    </div>
                                </form>
                            </div>

                            {{-- Hidden select (existing JS binds to .category's change event). --}}
                            <select name="category" class="category d-none">
                                <option value="">{{translate('All Categories')}}</option>
                                @foreach ($categories as $item)
                                    <option value="{{$item->id}}" {{$category==$item->id?'selected':''}}>{{ Str::limit($item->name, 40)}}</option>
                                @endforeach
                            </select>

                            {{-- Horizontal category pills. Clicking sets the hidden select + triggers change. --}}
                            <div class="pos-ix-pills" role="tablist">
                                <button type="button" class="pos-ix-pill pos-ix-cat {{ !$category ? 'is-active' : '' }}"
                                        data-category-id="">{{ translate('All') }}</button>
                                @foreach($categories as $item)
                                    <button type="button"
                                            class="pos-ix-pill pos-ix-cat {{ $category == $item->id ? 'is-active' : '' }}"
                                            data-category-id="{{ $item->id }}">{{ Str::limit($item->name, 30) }}</button>
                                @endforeach
                            </div>

                            {{-- Favorites hero row --}}
                            @if(isset($favorites) && $favorites->count())
                                <div class="pos-ix-favs">
                                    <div class="pos-ix-favs-label">⭐ {{ translate('Favorites') }}</div>
                                    <div class="pos-ix-favs-row">
                                        @foreach($favorites as $product)
                                            @include('admin-views.pos._single_product',['product'=>$product])
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Main product grid --}}
                            <div id="items" class="pos-ix-grid-wrap">
                                <div class="pos-item-wrap">
                                    @foreach($products as $product)
                                        @include('admin-views.pos._single_product',['product'=>$product])
                                    @endforeach
                                </div>
                            </div>

                            <div class="p-3 d-flex justify-content-end">
                                {!!$products->withQueryString()->links()!!}
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card h-100 rounded">
                            <div class="billing-section-wrap overflow-hidden">
                                <div class="pos-title ">
                                    <h4 class="mb-0 fs-16 text-title font-weight-normal">{{translate('Billing_Section')}}</h4>
                                </div>

                                <div class="p-2 p-sm-4 overflow-y-auto billing-section-wrap">
                                    <div class="bg-color4 rounded p-10px mb-20">
                                        <div class="form-group mb-2 d-flex gap-2">
                                            <div class="d-flex gap-2 m-0 position-relative w-100">
                                                <select id='customer' name="customer_id" data-placeholder="{{translate('Walk_In_Customer')}}"
                                                        class="js-data-example-ajax form-control form-ellipsis customer customer-select-index m-1">
                                                    <option value="0" {{ session()->get('customer_id') == null ? 'selected' : '' }}>{{translate('Walk_In_Customer')}}</option>
                                                    @foreach(\App\User::select('id', 'f_name', 'l_name', 'phone')->latest()->get() as $customer)
                                                        <option value="{{$customer['id']}}" {{ session()->get('customer_id') == $customer['id'] ? 'selected' : '' }}>{{$customer['f_name']. ' '. $customer['l_name'] }} ({{ $customer['phone'] }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="customer-card position-relative bg-white p-xl-3 p-md-2 p-0 d-none">
                                            <div class="d-flex justify-content-end position-absolute right-0 top-0 m-3">
                                                <a class="cursor-pointer text-c2" data-toggle="modal" data-target="#edit-customer">
                                                    <i class="tio-edit edit-icon" ></i>
                                                </a>
                                            </div>

                                            <div class="d-flex mb-1 gap-2">
                                                <span class="label min-w-60px fs-13 w-60px d-block"> {{ translate('Name') }} </span>
                                                <span>:</span>
                                                <span class="value customer-name fs-14 text-dark"></span>
                                            </div>

                                            <div class="d-flex mb-1 gap-2">
                                                <span class="label min-w-60px fs-13 w-60px d-block"> {{ translate('Contact') }} </span>
                                                <span>:</span>
                                                <span class="value customer-contact fs-14 text-dark"></span>
                                            </div>

                                            <div class="d-flex mb-1 gap-2">
                                                <span class="label min-w-60px fs-13 w-60px d-block"> {{ translate('Email') }} </span>
                                                <span>:</span>
                                                <span class="value customer-email fs-14 text-dark"></span>
                                            </div>

                                            <div class="d-flex gap-2">
                                                <span class="label min-w-60px fs-13 w-60px d-block"> {{ translate('Wallet') }} </span>
                                                <span>:</span>
                                                <span class="value customer-wallet fs-14 font-weight-bolder text-dark"></span>
                                            </div>
                                            <input type="hidden" name="available_wallet_balance" value="0">

                                        </div>
                                    </div>


                                    <div class="bg-color4 rounded p-10px mb-20">
                                        <div class="form-group mb-0">
                                            <label for="branch" class="font-weight-semibold fz-16 text-dark">{{translate('select_branch')}}</label>
                                            <select name="branch_id" class="js-select2-custom-x form-ellipsis form-control branch" id="change-branch">
                                                <option disabled selected>{{translate('select_branch')}}</option>
                                                @foreach($branches as $branch)
                                                    <option value="{{$branch['id']}}" {{ session()->get('branch_id') == $branch['id'] ? 'selected' : '' }}>{{$branch['name']}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="bg-color4 rounded p-10px mb-20">
                                        <div class="form-group mb-3">
                                            <label class="input-label font-weight-semibold fz-14 text-dark">{{translate('Select Order Type')}}</label>
                                            <div>
                                                <div class="form-control order_type_radio d-flex flex-xxl-nowrap flex-wrap justify-content-between">
                                                    <label class="custom-radio d-flex align-items-center m-0">
                                                        <input type="radio" class="order-type-radio" name="order_type" value="take_away" {{ session()->has('order_type') && session()->get('order_type') == 'take_away' ? 'checked' : '' }}>
                                                        <span class="media align-items-center mb-0">
                                                        <span class="media-body">{{translate('Take Away')}}</span>
                                                    </span>
                                                    </label>

                                                    <label class="custom-radio d-flex align-items-center m-0">
                                                        <input type="radio" class="order-type-radio" name="order_type" value="dine_in" {{ !session()->has('order_type') || session()->get('order_type') == 'dine_in' ? 'checked' : '' }}>
                                                        <span class="media align-items-center mb-0">
                                                        <span class="media-body">{{translate('Dine-In')}}</span>
                                                    </span>
                                                    </label>

                                                    <label class="custom-radio d-flex align-items-center m-0">
                                                        <input type="radio" class="order-type-radio" name="order_type" value="home_delivery" {{ session()->has('order_type') && session()->get('order_type') == 'home_delivery' ? 'checked' : '' }}>
                                                        <span class="media align-items-center mb-0">
                                                        <span class="media-body">{{translate('Home Delivery')}}</span>
                                                    </span>
                                                    </label>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="{{ (!session()->has('order_type') || session('order_type') == 'dine_in') ? '' : 'd-none' }}" id="dine_in_section">
                                            <div class="form-group mb-3 d-flex flex-wrap flex-sm-nowrap gap-2">
                                                <select name="table_id" class="js-select2-custom-x form-ellipsis form-control select-table">
                                                    <option disabled selected>{{translate('select_table')}}</option>
                                                    @foreach($tables as $table)
                                                        <option value="{{$table['id']}}" data-capacity="{{ $table['capacity'] }}" {{ session()->get('table_id') == $table['id'] ? 'selected' : '' }}>{{translate('table ')}} - {{$table['number']}}{{ $table['zone'] ? ' · ' . $table['zone'] : '' }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="form-group d-flex flex-wrap flex-sm-nowrap gap-2">
                                                <input type="number" value="{{ session('people_number') }}" name="number_of_people"  step="1"
                                                       id="number_of_people" class="form-control" min="1" max="99"
                                                       placeholder="{{translate('Number Of People')}}">
                                            </div>
                                        </div>
                                        <div class="form-group mb-0 card rounded border pt-2 pb-2 px-3 d-none" id="home_delivery_section">
                                            <div class="d-flex justify-content-between">
                                                <label for="" class="font-weight-semibold mb-0 fz-16 text-dark"><i class="tio-user-big fs-14"></i> {{translate(' Delivery Address')}}
                                                </label>
                                                <span class="edit-btn cursor-pointer" id="delivery_address" data-toggle="modal"
                                                      data-target="#AddressModal"><i class="tio-edit text-primary"></i>
                                            </span>
                                            </div>
                                            <div class="position-relative border-top pt-3 mt-2" id="del-add">
                                                @include('admin-views.pos._address')
                                            </div>
                                        </div>
                                    </div>

                                    <div class="">
                                        <div class='w-100 bg-white rounded overflow-hidden' id="cart">
                                            @include('admin-views.pos._cart')
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="modal fade" id="quick-view" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" id="quick-view-modal">

                </div>
            </div>
        </div>

        <div class="modal fade" id="add-customer" tabindex="-1">
            <div class="modal-dialog madl--lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fs-20">{{translate('Add_New_Customer')}}</h5>
                        <button type="button" class="close bg-color3 w-32px h-32px rounded-circle" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{route('admin.pos.customer-store')}}" method="post" id="customer-form">
                            @csrf
                            <div class="bg-color4 rounded p-3">
                                <div class="row pl-2">
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="input-label">
                                                {{translate('First_Name')}}
                                                <span class="input-label-secondary text-danger">*</span>
                                            </label>
                                            <input type="text" name="f_name" class="form-control" value="" placeholder="{{translate('First name')}}" required="">
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="input-label">
                                                {{translate('Last_Name')}}
                                                <span class="input-label-secondary text-danger">*</span>
                                            </label>
                                            <input type="text" name="l_name" class="form-control" value="" placeholder="{{translate('Last name')}}" required="">
                                        </div>
                                    </div>
                                </div>
                                <div class="row pl-2">
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="input-label">
                                                {{translate('Email Address')}}
                                                <span class="input-label-secondary text-danger">*</span>
                                            </label>
                                            <input type="email" name="email" class="form-control" value="" placeholder="{{translate('Ex : ex@example.com')}}" required="">
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="input-label">
                                                {{translate('Phone Number')}}
                                                <span class="input-label-secondary text-danger">*</span>
                                            </label>
                                            <input type="tel" name="phone" class="form-control"
                                                   placeholder="{{translate('Ex : +88017*****')}}"
                                                   required
                                                   oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-4 gap-2">
                                <button type="button" class="btn btn-secondary mr-1 min-w-120px" data-dismiss="modal">{{translate('Cancel')}}</button>
                                <button type="submit" id="" class="btn btn-primary min-w-120px">{{translate('Submit')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="edit-customer" tabindex="-1">
            <div class="modal-dialog madl--lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fs-20">{{translate('customer info')}}</h5>
                        <button type="button" class="close bg-color3 w-32px h-32px rounded-circle" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{route('admin.pos.customer.update')}}" method="post" id="customer-edit-form">
                            @csrf
                            <div class="bg-color4 rounded p-3">
                                <input type="hidden" name="customer_id" value="">
                                <div class="row pl-2">
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('First_Name')}}
                                                <span class="input-label-secondary text-danger">*</span>
                                            </label>
                                            <input type="text" name="f_name" class="form-control" value="" placeholder="{{translate('First name')}}" required="">
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Last_Name')}}
                                                <span class="input-label-secondary text-danger">*</span>
                                            </label>
                                            <input type="text" name="l_name" class="form-control" value="" placeholder="{{translate('Last name')}}" required="">
                                        </div>
                                    </div>
                                </div>
                                <div class="row pl-2">
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Email Address')}}
                                                <span class="input-label-secondary text-danger">*</span>
                                            </label>
                                            <input type="email" name="email" class="form-control" value="" placeholder="{{translate('Ex : ex@example.com')}}" required="">
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Phone Number')}}
                                                <span class="input-label-secondary text-danger">*</span>
                                            </label>
                                            <input type="tel" name="phone" class="form-control" value="" placeholder="{{translate('Ex : +88017*****')}}" required="" disabled
                                                   oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button type="button" class="btn btn-secondary mr-1 min-w-120px" data-dismiss="modal">{{translate('Cancel')}}</button>
                                <button type="submit" id="" class="btn btn-primary min-w-120px">{{translate('update')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>


        @php $order=\App\Model\Order::find(session('last_order')); @endphp
        @if($order)
            @php session(['last_order'=> false]); @endphp
            <div class="modal fade" id="print-invoice" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header flex-column">
                            <div class="w-100 d-flex justify-content-between align-items-center gap-3">
                                <h5 class="modal-title">{{translate('Print Invoice')}}</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="mt-4">
                                <center>
                                    <input type="button" class="btn btn-primary non-printable print-button" value="{{translate('Proceed, If thermal printer is ready.')}}"/>
                                    <a href="{{url()->previous()}}" class="btn btn-danger non-printable">{{translate('Back')}}</a>
                                </center>
                                <hr class="non-printable">
                            </div>
                        </div>
                        <div class="modal-body row ff-emoji overflow-auto pt-0">
                            <div class="row m-auto" id="printableArea">
                                @include('admin-views.pos.order.invoice')
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="modal fade p-0" id="AddressModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered madl--lg">
                <div class="modal-content">
                    <div class="modal-header pt-4">
                        <h5 class="modal-title fs-20">{{ translate('Delivery Information') }}</h5>
                        <button type="button" class="close bg-color3 w-32px h-32px rounded-circle" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body pt-3">
                        <?php
                        if(session()->has('address')) {
                            $old = session()->get('address');
                        }else {
                            $old = null;
                        }
                        ?>
                        <form id='delivery_address_store'>
                            @csrf
                            <div class="bg-color4 rounded p-3">
                                <div class="row g-2" id="delivery_address">

                                    <div class="col-md-12">
                                        <label class="input-label" for="">{{ translate('Address Type') }}
                                            <span class="input-label-secondary text-danger">*</span>
                                        </label>
                                        <select name="address_type" class="custom-select" required>
                                            <option value="" disabled {{ empty($old['address_type']) ? 'selected' : '' }}>Select from dropdown</option>
                                            <option value="Home" {{ (isset($old['address_type']) && $old['address_type'] == 'Home') ? 'selected' : '' }}>Home</option>
                                            <option value="Workplace" {{ (isset($old['address_type']) && $old['address_type'] == 'Workplace') ? 'selected' : '' }}>Workplace</option>
                                            <option value="Others" {{ (isset($old['address_type']) && $old['address_type'] == 'Others') ? 'selected' : '' }}>Others</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="input-label" for="">{{ translate('Name') }}
                                            <span class="input-label-secondary text-danger">*</span></label>
                                        <input type="text" class="form-control" name="contact_person_name"
                                               value="{{ $old ? $old['contact_person_name'] : '' }}" placeholder="{{ translate('Ex :') }} {{translate('Jhon')}}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="input-label" for="">{{ translate('Contact Number') }}
                                            <span class="input-label-secondary text-danger">*</span>
                                        </label>
                                        <input type="tel" class="form-control" name="contact_person_number"
                                               value="{{ $old ? $old['contact_person_number'] : '' }}"  placeholder="{{ translate('Ex :') }} +3264124565" required
                                               oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="input-label" for="">{{ translate('Road') }}</label>
                                        <input type="text" class="form-control" name="road" value="{{ $old ? $old['road'] : '' }}"  placeholder="{{ translate('Ex :') }} 4th">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="input-label" for="">{{ translate('House') }}</label>
                                        <input type="text" class="form-control" name="house" value="{{ $old ? $old['house'] : '' }}" placeholder="{{ translate('Ex :') }} 45/C">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="input-label" for="">{{ translate('Floor') }}</label>
                                        <input type="text" class="form-control" name="floor" value="{{ $old ? $old['floor'] : '' }}"  placeholder="{{ translate('Ex :') }} 1A">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="input-label">{{ translate('address') }}
                                            <span class="input-label-secondary text-danger">*</span>
                                        </label>
                                        <textarea name="address" id="address" class="form-control" required>{{ $old ? $old['address'] : '' }}</textarea>
                                    </div>

                                    <?php
                                        $branchId =(int) session('branch_id') ?? 1;
                                        $branch = \App\Model\Branch::with(['delivery_charge_setup', 'delivery_charge_by_area'])
                                            ->where(['id' => $branchId])
                                            ->first(['id', 'name', 'status']);

                                        $deliveryType = $branch->delivery_charge_setup->delivery_charge_type ?? 'fixed';
                                        $deliveryType = $deliveryType == 'area' ? 'area' : ($deliveryType == 'distance' ? 'distance' : 'fixed');

                                        if (isset($branch->delivery_charge_setup) && $branch->delivery_charge_setup->delivery_charge_type == 'distance') {
                                            unset($branch->delivery_charge_by_area);
                                            $branch->delivery_charge_by_area = [];
                                        }
                                    ?>

                                    @php $googleMapStatus = \App\CentralLogics\Helpers::get_business_settings('google_map_status'); @endphp
                                    @if($googleMapStatus)
                                        @if($deliveryType == 'distance')
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-primary">
                                                        {{ translate('* pin the address in the map to calculate delivery fee') }}
                                                    </span>
                                                </div>
                                                <div class="position-relative">
                                                    <div id="location_map_div">
                                                        <input id="pac-input" class="controls rounded initial-8"
                                                               title="{{ translate('search_your_location_here') }}" type="text"
                                                               placeholder="{{ translate('search_here') }}" />
                                                        <div id="location_map_canvas" class="overflow-hidden rounded custom-height"></div>
                                                    </div>
                                                    <div class="lat-long-adjust position-absolute  bottom-0 mb-2 bg-white d-sm-inline-flex align-items-center justify-content-center mx-1 gap-1">
                                                        <div class="d-flex align-items-center gap-1">
                                                            <label class="input-label m-0 fs-10 text-dark" for="">{{ translate('lat:') }}</label>
                                                            <input type="text" class=" w-auto border-0 outline-0 fs-10 text-dark" id="latitude" name="latitude"
                                                                    value="{{ $old ? $old['latitude'] : '' }}" readonly required>
                                                        </div>
                                                        <div class="line d-sm-block d-none"></div>
                                                        <div class="d-flex align-items-center gap-1">
                                                            <label class="input-label m-0 fs-10 text-dark" for="">{{ translate('log:') }}</label>
                                                            <input type="text" class="w-auto border-0 outline-0 fs-10 text-dark" id="longitude" name="longitude"
                                                                value="{{ $old ? $old['longitude'] : '' }}" readonly required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endif

                                    @if($deliveryType == 'area')
                                        <div class="col-md-6">
                                            <label class="input-label">{{ translate('Delivery Area') }}</label>
                                            <select name="selected_area_id" class="form-control js-select2-custom-x mx-1" id="areaDropdown" >
                                                <option value="">{{ translate('Select Area') }}</option>
                                                @foreach($branch->delivery_charge_by_area as $area)
                                                    <option value="{{$area['id']}}" {{ (isset($old) && $old['area_id'] == $area['id']) ? 'selected' : '' }} data-charge="{{$area['delivery_charge']}}" >{{ $area['area_name'] }} - ({{ Helpers::set_symbol($area['delivery_charge']) }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="input-label" for="">{{ translate('Delivery Charge') }} ({{ Helpers::currency_symbol() }})</label>
                                            <input type="number" class="form-control" name="delivery_charge" id="deliveryChargeInput" value="" readonly>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-12 mt-4">
                                <div class="btn--container justify-content-end gap-2">
                                    <button type="button" class="btn btn-secondary min-w-120px" data-dismiss="modal">{{translate('Cancel')}}</button>
                                    <button class="btn btn-sm btn-primary min-w-120px delivery-address-update-button" type="button" data-dismiss="modal">
                                        {{ translate('Save') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="warningModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-body bg-FEF7D1 p-3 rounded-10">
                        <div class="d-flex gap-2 align-items-center">
                            <span class="fz-24 text-warning">
                                <i class="tio-warning"></i>
                            </span>
                            <div class="flex-grow-1 d-flex gap-4 align-items-center justify-content-center">
                                <div>
                                    <h4 class="fz-16 text-black-50">Warning</h4>
                                    <p class="mb-2 fz-12 text-black-50">There isn’t enough quantity on stock. Only 3 is available.</p>
                                </div>
                                <button type="button" class="close fz-28 text-title" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">
                                        <i class="tio-clear"></i>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Order Placed confirmation modal --}}
    <div class="modal fade" id="orderPlacedModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-center">
                <div class="modal-body p-4">
                    <div style="font-size: 64px; line-height: 1; color: #28a745; margin-bottom: 12px;">&#10004;</div>
                    <h3 class="mb-2">{{ translate('Order Placed') }}</h3>
                    <p class="mb-1 text-muted">{{ translate('Order ID') }}: <strong id="op-order-id">—</strong></p>
                    <p class="mb-1 text-muted" id="op-table-line" style="display:none;">
                        {{ translate('Table') }}: <strong id="op-table">—</strong>
                    </p>
                    <p class="fz-18 mb-3"><strong id="op-amount">—</strong></p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-warning font-weight-bold px-4" id="op-send-kitchen">
                            <i class="tio-print"></i> {{ translate('Send to Kitchen') }}
                        </button>
                        <button type="button" class="btn btn-primary px-4" data-dismiss="modal">
                            {{ translate('Next Customer') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cart-related modals rendered ONCE outside the AJAX-swapped #cart container.
         Keeping them here (instead of inside _cart.blade.php) prevents Bootstrap's
         modal backdrop from getting orphaned when the cart partial refreshes. --}}
    @include('admin-views.pos._cart-modals')
@endsection

@push('script_2')

    {{-- jQuery, vendor.min, theme.min, sweet_alert, toastr are all already loaded by
         the admin layout (app.blade.php). Reloading jQuery here was resetting $.fn and
         wiping out plugins registered on the first jQuery — specifically `.owlCarousel`
         used by the quick-view product modal, which caused the whole POS to freeze on
         any modal open. Kept only the page-specific Google Maps script below. --}}
    <script src="https://maps.googleapis.com/maps/api/js?key={{ \App\Model\BusinessSetting::where('key', 'map_api_client_key')->first()?->value }}&libraries=places&v=3.51"></script>


    @if ($errors->any())
        <script>
            "use strict";

            @foreach($errors->all() as $error)
            toastr.error('{{$error}}', Error, {
                CloseButton: true,
                ProgressBar: true
            });
            @endforeach
        </script>
    @endif

    <script>
        "use strict";

        $('.print-button').click(function() {
            printDiv('printableArea');
        });

        $('.delivery-address-update-button').click(function() {
            deliveryAdressStore();
        });

        $('.quick-view-trigger').click(function() {
            var productId = $(this).data('product-id');
            quickView(productId);
        });

        $('.category').change(function() {
            var selectedCategory = $(this).val();
            set_category_filter(selectedCategory);
        });

        // Category pill clicks → drive the hidden select so existing behaviour stays.
        $(document).on('click', '.pos-ix-cat', function () {
            var id = $(this).data('category-id') || '';
            $('.pos-ix-cat').removeClass('is-active');
            $(this).addClass('is-active');
            $('.category').val(id).trigger('change');
        });

        $(document).ready(function() {

            let currencySymbol = '{{ \App\CentralLogics\Helpers::currency_symbol() }}';
            let $customerCard = $('.customer-card');
            let $customerForm = $('#customer-edit-form');
            let $customerWalletInfoCard = $('.customer-wallet-info-card');
            let $customerWalletRemainingCard = $('.wallet-remaining-card');

            // the customer session — always updated dynamically
            let currentCustomerId = '{{ session("customer_id") ?? "" }}';

            function fetchAndShowCustomerCard(id) {
                if (!id) return;

                $.ajax({
                    url: '{{ route("admin.pos.get-customer-details") }}',
                    type: 'GET',
                    data: { id: id },
                    success: function(data) {

                        $('.customer-name').text(data.name);
                        $('.customer-contact').text(data.phone);
                        $('.customer-email').text(data.email);
                        $('.customer-wallet').text(data.wallet + currencySymbol);

                        $customerCard.removeClass('d-none');

                        if (data.wallet > 0){
                            $('#wallet_payment_li').removeClass('d-none');
                        } else {
                            $('#wallet_payment_li').addClass('d-none');
                            $customerWalletInfoCard.addClass('d-none');
                        }

                        $customerWalletInfoCard.find('input[name="phone"]').val(data.phone);
                        $customerWalletInfoCard.find('.available-wallet-balance').text(data.wallet);
                        $customerCard.find('input[name="available_wallet_balance"]').val(data.wallet)
                    }
                });
            }

            // Load customer if session has one
            if (currentCustomerId) {
                $('.customer').val(currentCustomerId).trigger('change');
                fetchAndShowCustomerCard(currentCustomerId);
            }

            // When customer dropdown changes
            $('.customer').change(function() {

                let selectedCustomerId = $(this).val();
                selectedCustomerId = selectedCustomerId == 0 ? null : selectedCustomerId;

                $.post({
                    url: '{{ route('admin.pos.change-customer') }}',
                    data: {
                        _token: '{{ csrf_token() }}',
                        value: selectedCustomerId,
                    },
                    success: function (data) {

                        // UPDATE dynamic customer ID
                        currentCustomerId = data.customer_id;

                        if (currentCustomerId) {
                            fetchAndShowCustomerCard(currentCustomerId);
                        } else {
                            $customerCard.addClass('d-none');
                            $('#wallet_payment_li').addClass('d-none');
                        }

                        // Replace cart HTML
                        $('#cart').empty().html(data.view);

                        toastr.success('Customer selected successfully', {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    },
                });
            });

            // Edit customer modal load
            $customerCard.on('click', '.edit-icon', function() {

                if (!currentCustomerId) return;

                $.ajax({
                    url: '{{ route("admin.pos.get-customer-details") }}',
                    type: 'GET',
                    data: { id: currentCustomerId },
                    success: function(data) {

                        $customerForm.find('input[name="customer_id"]').val(data.id);
                        $customerForm.find('input[name="f_name"]').val(data.f_name);
                        $customerForm.find('input[name="l_name"]').val(data.l_name);
                        $customerForm.find('input[name="email"]').val(data.email);
                        $customerForm.find('input[name="phone"]').val(data.phone);

                        $('#edit-customer').modal('show');
                    }
                });
            });
        });


        function applyWalletVisibility(customerId) {

            if (!customerId) {
                $('#wallet_payment_li').addClass('d-none');
                return;
            }

            $.ajax({
                url: '{{ route("admin.pos.get-customer-details") }}',
                type: 'GET',
                data: { id: customerId },
                success: function(data) {
                    if (data.wallet > 0){
                        $('#wallet_payment_li').removeClass('d-none');
                    } else {
                        $('#wallet_payment_li').addClass('d-none');
                    }
                }
            });
        }

        //Customer Select inside modal
        $(document).ready(function () {
            $('#customer.customer-select-index').select2({
                placeholder: "Select a customer",
                allowClear: true,
                dropdownCssClass: "select2-dropdown-index custom-select2-dropdown"
            })
            .on('change', function () {
                toggleClearButton();
            })
            .on('select2:open', function () {
                let dropdown = $('.select2-dropdown.custom-select2-dropdown');
                if (dropdown.find('.custom-add-button').length === 0) {
                    let $searchfield = dropdown.find('.select2-search.select2-search--dropdown');
                    let $button = $('<button type="button" class="custom-add-button d-flex align-items-center justify-content-end gap-1 btn p-0 border-0 text-c2 fs-14" style="width: 100%; padding-right: 10px !important; margin-top: 12px; margin-bottom: 8px; text-decoration: underline" data-toggle="modal" data-target="#add-customer" title="Add Customer" id="add_new_customer">+ Add New Customer</button>');
                    $searchfield.append($button);
                }
            });
            toggleClearButton();
            function toggleClearButton() {
                let val = $('select[name="customer_id"]').val();
                if (val == 0 || val === null) {
                    $('.select2-selection__clear').addClass('d-none');
                } else {
                    $('.select2-selection__clear').removeClass('d-none');
                }
            }
        });
        //End


        $('.branch').change(function() {
            var selectedBranchId = $(this).val();
            store_key('branch_id', selectedBranchId);
        });

        $('.order-type-radio').change(function() {
            var selectedOrderType = $(this).val();
            // Mirror the choice into the form's hidden input immediately so the
            // Place-Order POST carries it regardless of AJAX session timing.
            $('#form_order_type').val(selectedOrderType);
            select_order_type(selectedOrderType);
        });

        $('.select-table').change(function() {
            var $selected = $(this).find(':selected');
            var selectedTableId = $selected.val();
            store_key('table_id', selectedTableId);

            // Auto-fill number of people from table capacity (cashier can still edit)
            var capacity = parseInt($selected.data('capacity'), 10);
            if (capacity > 0) {
                $('#number_of_people').val(capacity);
                store_key('people_number', capacity);
            }
        });

        $('#number_of_people').keyup(function() {
            var numberOfPeople = $(this).val().replace(/[^\d]/g, '');
            $(this).val(numberOfPeople);
            store_key('people_number', numberOfPeople);
        });

        $('.sign-out-trigger').click(function(event) {
            event.preventDefault();
            Swal.fire({
                title: '{{translate('Do you want to logout')}}?',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonColor: '#E67E22',
                cancelButtonColor: '#363636',
                confirmButtonText: '{{translate('Yes')}}',
                denyButtonText: `{{translate('Do not Logout')}}`
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '{{route('admin.auth.logout')}}';
                } else {
                    Swal.fire('Canceled', '', 'info');
                }
            });
        });

        // Print Invoice auto-popup removed — the order-placed popup below now handles
        // post-submit UX (with Send to Kitchen). Print/receipt happens at Checkout.

        function printDiv(divName) {

            if($('html').attr('dir') === 'rtl') {
                $('html').attr('dir', 'ltr')
                var printContents = document.getElementById(divName).innerHTML;
                var originalContents = document.body.innerHTML;
                document.body.innerHTML = printContents;
                $('#printableAreaContent').attr('dir', 'rtl')
                window.print();
                document.body.innerHTML = originalContents;
                $('html').attr('dir', 'rtl')
                location.reload();
            }else{
                var printContents = document.getElementById(divName).innerHTML;
                var originalContents = document.body.innerHTML;
                document.body.innerHTML = printContents;
                window.print();
                document.body.innerHTML = originalContents;
                location.reload();
            }

        }

        function set_category_filter(id) {
            var nurl = new URL('{!!url()->full()!!}');
            nurl.searchParams.set('category_id', id);
            location.href = nurl;
        }


        $('#search-form').on('submit', function (e) {
            e.preventDefault();
            var keyword= $('#datatableSearch').val();
            var nurl = new URL('{!!url()->full()!!}');
            nurl.searchParams.set('keyword', keyword);
            location.href = nurl;
        });

        function addon_quantity_input_toggle(e)
        {
            var cb = $(e.target);
            if(cb.is(":checked"))
            {
                cb.siblings('.addon-quantity-input').css({'visibility':'visible'});
            }
            else
            {
                cb.siblings('.addon-quantity-input').css({'visibility':'hidden'});
            }
        }
        function quickView(product_id) {
            $.ajax({
                url: '{{route('admin.pos.quick-view')}}',
                type: 'GET',
                data: {
                    product_id: product_id
                },
                dataType: 'json',
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function () {
                    $('#loading').hide();
                },
            });

        }

        function checkAddToCartValidity() {
            return true;
        }

        function cartQuantityInitialize() {
            $('.btn-number').click(function (e) {
                e.preventDefault();

                var fieldName = $(this).attr('data-field');
                var type = $(this).attr('data-type');
                var stock_type = $(this).attr('data-stock_type');
                var input = $("input[name='" + fieldName + "']");
                var currentVal = parseInt(input.val());
                var minVal = parseInt(input.attr('min'));
                var maxVal = parseInt(input.attr('max'));

                if (!isNaN(currentVal)) {
                    if (type === 'minus') {
                        if (currentVal > minVal) {
                            input.val(currentVal - 1).change();
                        }
                        if (parseInt(input.val()) <= minVal) {
                            $(this).attr('disabled', true);
                        }

                        // Enable plus button when minus clicked
                        $(".btn-number[data-type='plus'][data-field='" + fieldName + "']").removeAttr('disabled');
                    }
                    else if (type === 'plus') {
                        if (stock_type === 'unlimited' || currentVal < maxVal) {
                            input.val(currentVal + 1).change();
                            $(".btn-number[data-type='minus'][data-field='" + fieldName + "']").removeAttr('disabled');
                        }

                        if (stock_type !== 'unlimited' && currentVal + 1 >= maxVal + 1) {
                            $(this).attr('disabled', true);

                            Swal.fire({
                                icon: 'warning',
                                title: '{{ translate("Cart") }}',
                                text: '{{ translate("You have reached the maximum available stock.") }}',
                                confirmButtonText: '{{ translate("OK") }}'
                            });
                        }
                    }
                } else {
                    input.val(1);
                }
            });

            $('.input-number').focusin(function () {
                $(this).data('oldValue', $(this).val());
            });

            $('.input-number').change(function () {

                var minValue = parseInt($(this).attr('min'));
                var maxValue = parseInt($(this).attr('max'));
                var stock_type = $("button[data-field='" + $(this).attr('name') + "']").data('stock_type');
                var valueCurrent = parseInt($(this).val());
                var name = $(this).attr('name');

                if (valueCurrent >= minValue) {
                    $(".btn-number[data-type='minus'][data-field='" + name + "']").removeAttr('disabled')
                } else {
                    Swal.fire({
                        icon: 'error',
                        title:'{{translate("Cart")}}',
                        text: '{{translate('Sorry, the minimum value was reached')}}'
                    });
                    $(this).val($(this).data('oldValue'));
                }

                if (valueCurrent < minValue) {
                    Swal.fire({
                        icon: 'error',
                        title: '{{translate("Cart")}}',
                        text: '{{translate("Sorry, the minimum value was reached")}}'
                    });
                    $(this).val($(this).data('oldValue'));
                    return;
                }

                if (stock_type !== 'unlimited' && valueCurrent > maxValue) {
                    Swal.fire({
                        icon: 'error',
                        title: '{{translate("Cart")}}',
                        confirmButtonText: '{{translate("Ok")}}',
                        text: '{{translate("Sorry, stock limit exceeded")}}.'
                    });
                    $(this).val($(this).data('oldValue'));
                    return;
                }

                $(".btn-number[data-type='minus'][data-field='" + name + "']").removeAttr('disabled');
                $(".btn-number[data-type='plus'][data-field='" + name + "']").removeAttr('disabled');
            });

            $(".input-number").keydown(function (e) {
                if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
                    (e.keyCode == 65 && e.ctrlKey === true) ||
                    (e.keyCode >= 35 && e.keyCode <= 39)) {
                    return;
                }
                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                    e.preventDefault();
                }
            });
        }

        function getVariantPrice() {
            if ($('#add-to-cart-form input[name=quantity]').val() > 0 && checkAddToCartValidity()) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });
                $.ajax({
                    type: "POST",
                    url: '{{ route('admin.pos.variant_price') }}',
                    data: $('#add-to-cart-form').serializeArray(),
                    success: function (data) {
                        if(data.error == 'quantity_error'){
                            toastr.error(data.message);
                        }
                        else{
                            $('#add-to-cart-form #chosen_price_div').removeClass('d-none');
                            $('#add-to-cart-form #chosen_price_div #chosen_price').html(data.price);
                        }
                    }
                });
            }
        }

        function addToCart(form_id = 'add-to-cart-form') {
            if (checkAddToCartValidity()) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });
                $.post({
                    url: '{{ route('admin.pos.add-to-cart') }}',
                    data: $('#' + form_id).serializeArray(),
                    beforeSend: function () {
                        $('#loading').show();
                    },
                    success: function (data) {
                        if (data.data == 1) {
                            Swal.fire({
                                confirmButtonColor: '#E67E22',
                                icon: 'info',
                                title: '{{translate("Cart")}}',
                                confirmButtonText:'{{translate("Ok")}}',
                                text: "{{translate('Product already added in cart')}}"
                            });
                            return false;
                        } else if (data.data == 0) {
                            Swal.fire({
                                confirmButtonColor: '#E67E22',
                                icon: 'error',
                                title: '{{translate("Cart")}}',
                                confirmButtonText:'{{translate("Ok")}}',
                                text: '{{translate('Sorry, product out of stock')}}.'
                            });
                            return false;
                        }
                        else if (data.data == 'variation_error') {
                            Swal.fire({
                                confirmButtonColor: '#E67E22',
                                icon: 'error',
                                title: 'Cart',
                                text: data.message
                            });
                            return false;
                        }
                        else if (data.data == 'stock_limit') {
                            Swal.fire({
                                confirmButtonColor: '#E67E22',
                                icon: 'error',
                                title: 'Cart',
                                text: data.message
                            });
                            return false;
                        }
                        $('.call-when-done').click();

                        toastr.success('{{translate('Item has been added in your cart')}}!', {
                            CloseButton: true,
                            ProgressBar: true
                        });

                        updateCart();
                    },
                    complete: function () {
                        $('#loading').hide();
                    }
                });
            } else {
                Swal.fire({
                    confirmButtonColor: '#E67E22',
                    type: 'info',
                    title: '{{translate("Cart")}}',
                    confirmButtonText:'{{translate("Ok")}}',
                    text: '{{translate('Please choose all the options')}}'
                });
            }
        }

        function removeFromCart(key) {
            $.post('{{ route('admin.pos.remove-from-cart') }}', {_token: '{{ csrf_token() }}', key: key}, function (data) {
                if (data.errors) {
                    for (var i = 0; i < data.errors.length; i++) {
                        toastr.error(data.errors[i].message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                } else {
                    updateCart();
                    toastr.info('{{translate('Item has been removed from cart')}}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }

            });
        }

        function emptyCart() {
            $.post('{{ route('admin.pos.emptyCart') }}', {_token: '{{ csrf_token() }}'}, function (data) {
                updateCart();
                toastr.info('{{translate('Item has been removed from cart')}}', {
                    CloseButton: true,
                    ProgressBar: true
                });
                location.reload();
            });
        }

        function updateCart() {
            $.post('<?php echo e(route('admin.pos.cart_items')); ?>', {_token: '<?php echo e(csrf_token()); ?>'}, function (data) {
                $('#cart').empty().html(data);

            });
        }

        $(function(){
            $(document).on('click','input[type=number]',function(){ this.select(); });
        });


        function updateQuantity(e){
            var element = $( e.target );
            var minValue = parseInt(element.attr('min'));
            var valueCurrent = parseInt(element.val());

            var key = element.data('key');
            if (valueCurrent >= minValue) {
                $.post('{{ route('admin.pos.updateQuantity') }}', {_token: '{{ csrf_token() }}', key: key, quantity:valueCurrent}, function (data) {
                    updateCart();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '{{translate("Cart")}}',
                    confirmButtonText:'{{translate("Ok")}}',
                    text: '{{translate('Sorry, the minimum value was reached')}}'
                });
                element.val(element.data('oldValue'));
            }
            if(e.type == 'keydown')
            {
                if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
                    (e.keyCode == 65 && e.ctrlKey === true) ||
                    (e.keyCode >= 35 && e.keyCode <= 39)) {
                    return;
                }
                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                    e.preventDefault();
                }
            }
        }

        $('.branch-data-selector').select2();
        $('.table-data-selector').select2();

        $('#order_place').submit(function(eventObj) {
            if($('#customer').val())
            {
                $(this).append('<input type="hidden" name="user_id" value="'+$('#customer').val()+'" /> ');
            }
            return true;
        });

        function store_key(key, value) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': "{{csrf_token()}}"
                }
            });
            $.post({
                url: '{{route('admin.pos.store-keys')}}',
                data: {
                    key:key,
                    value:value,
                },
                success: function (data) {
                    var selected_field_text = key;
                    var selected_field = selected_field_text.replace("_", " ");
                    var selected_field = selected_field.replace("id", " ");
                    var message = selected_field+' '+'selected!';
                    var new_message = message.charAt(0).toUpperCase() + message.slice(1);

                    toastr.success((new_message), {
                        CloseButton: true,
                        ProgressBar: true
                    });
                },

            });
        };


        $(document).ready(function (){
            $('#change-branch').on('change', function (){

                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $.ajax({
                    type: 'POST',
                    url: "{{ url('admin/pos/session-destroy') }}",
                    success: function() {
                        location.reload();
                    }
                });
            });
        });

        $(document).ready(function() {
            var orderType = {!! json_encode(session('order_type')) !!};

            // Default to dine_in when nothing stored (new UX).
            if (orderType === null || orderType === undefined || orderType === 'dine_in') {
                $('#dine_in_section').removeClass('d-none');
                $('#home_delivery_section').addClass('d-none');
            } else if (orderType === 'home_delivery') {
                $('#home_delivery_section').removeClass('d-none');
                $('#dine_in_section').addClass('d-none');
            } else {
                $('#home_delivery_section').addClass('d-none');
                $('#dine_in_section').addClass('d-none');
            }
        });

        function select_order_type(order_type) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': "{{csrf_token()}}"
                }
            });
            $.post({
                url: '{{route('admin.pos.order_type.store')}}',
                data: {
                    order_type:order_type,
                },
                success: function (data) {
                    updateCart();
                },
            });

            if (order_type == 'dine_in') {
                $('#dine_in_section').removeClass('d-none');
                $('#home_delivery_section').addClass('d-none')
            } else if(order_type == 'home_delivery') {
                $('#home_delivery_section').removeClass('d-none');
                $('#dine_in_section').addClass('d-none');
            }else{
                $('#home_delivery_section').addClass('d-none')
                $('#dine_in_section').addClass('d-none');
            }
        }


        // Update paid-by radio button handler
        $('.paid-by').change(function() {
            var selectedPaymentOption = $(this).val();
            let $customerWalletInfoCard = $('.customer-wallet-info-card');
            let $customerWalletRemainingCard = $('.wallet-remaining-card');
            let $customerCard = $('.customer-card');

            let currencySymbol = '{{ \App\CentralLogics\Helpers::currency_symbol() }}';
            let selectedCustomerId = '{{ session("customer_id") ?? "" }}';

            var totalOrderAmount = $('.hidden-paid-amount').val();

            if (selectedPaymentOption == 'pay_after_eating' || selectedPaymentOption === 'wallet_payment') {
                $('.collect-cash-section').addClass('d-none');
            } else {
                $('.collect-cash-section').removeClass('d-none');
            }

            if (selectedPaymentOption == 'wallet_payment') {
                $customerWalletInfoCard.removeClass('d-none');

                let totalOrderAmount = parseFloat($('.hidden-paid-amount').val()) || 0;
                let walletAmount = parseFloat(($customerCard.find('input[name="available_wallet_balance"]')).val()) || 0;

                let remainingAmount = totalOrderAmount - walletAmount;
                let usedWalletAmount = walletAmount > totalOrderAmount ? totalOrderAmount : walletAmount

                remainingAmount = remainingAmount.toFixed(2);
                usedWalletAmount = usedWalletAmount.toFixed(2);
                walletAmount = walletAmount.toFixed(2);

                $customerWalletRemainingCard.find('.paid-by-wallet-amount').text(walletAmount + currencySymbol);
                $customerWalletRemainingCard.find('.remaining-order-amount').text(remainingAmount + currencySymbol);

                $customerWalletInfoCard.find('.available-wallet-balance').text(walletAmount + currencySymbol);
                $customerWalletInfoCard.find('.used-wallet-amount').text('(Used ' + usedWalletAmount + currencySymbol + ')');

                if (selectedCustomerId && totalOrderAmount > 0 && totalOrderAmount > walletAmount) {
                    $customerWalletRemainingCard.removeClass('d-none');
                } else {
                    $customerWalletRemainingCard.addClass('d-none');
                }

            } else {
                $customerWalletInfoCard.addClass('d-none');
                $customerWalletRemainingCard.addClass('d-none');
            }

            if (selectedPaymentOption == 'card') {
                $('#paid-amount').attr('readonly', true);
                $('#paid-amount').addClass('bg-F5F5F5');
                // Reset paid amount to order amount
                $('#paid-amount').val(totalOrderAmount);
                calculateAmountDifference();
            } else {
                $('#paid-amount').removeAttr('readonly');
                $('#paid-amount').removeClass('bg-F5F5F5');
            }
        });


        $( document ).ready(function() {
            function initAutocomplete() {
                // Guard: the map canvas only exists inside the home-delivery address modal.
                // Without this check, `new google.maps.Map(null, ...)` throws InvalidValueError
                // and halts every subsequent ready handler — breaking modal/event wiring.
                var canvas = document.getElementById("location_map_canvas");
                if (!canvas) return;
                if (typeof google === 'undefined' || !google.maps) return;

                var myLatLng = {

                    lat: 23.811842872190343,
                    lng: 90.356331
                };
                const map = new google.maps.Map(canvas, {
                    center: {
                        lat: 23.811842872190343,
                        lng: 90.356331
                    },
                    zoom: 13,
                    mapTypeId: "roadmap",
                });

                var marker = new google.maps.Marker({
                    position: myLatLng,
                    map: map,
                });

                marker.setMap(map);
                var geocoder = geocoder = new google.maps.Geocoder();
                google.maps.event.addListener(map, 'click', function(mapsMouseEvent) {
                    var coordinates = JSON.stringify(mapsMouseEvent.latLng.toJSON(), null, 2);
                    var coordinates = JSON.parse(coordinates);
                    var latlng = new google.maps.LatLng(coordinates['lat'], coordinates['lng']);
                    marker.setPosition(latlng);
                    map.panTo(latlng);

                    document.getElementById('latitude').value = coordinates['lat'];
                    document.getElementById('longitude').value = coordinates['lng'];

                    geocoder.geocode({
                        'latLng': latlng
                    }, function(results, status) {
                        if (status == google.maps.GeocoderStatus.OK) {
                            if (results[1]) {
                                document.getElementById('address').value = results[1].formatted_address;
                            }
                        }
                    });
                });

                const input = document.getElementById("pac-input");
                const searchBox = new google.maps.places.SearchBox(input);
                map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);

                map.addListener("bounds_changed", () => {
                    searchBox.setBounds(map.getBounds());
                });
                let markers = [];

                searchBox.addListener("places_changed", () => {
                    const places = searchBox.getPlaces();

                    if (places.length == 0) {
                        return;
                    }

                    markers.forEach((marker) => {
                        marker.setMap(null);
                    });
                    markers = [];

                    const bounds = new google.maps.LatLngBounds();
                    places.forEach((place) => {
                        if (!place.geometry || !place.geometry.location) {
                            return;
                        }
                        var mrkr = new google.maps.Marker({
                            map,
                            title: place.name,
                            position: place.geometry.location,
                        });
                        google.maps.event.addListener(mrkr, "click", function(event) {
                            document.getElementById('latitude').value = this.position.lat();
                            document.getElementById('longitude').value = this.position.lng();

                        });

                        markers.push(mrkr);

                        if (place.geometry.viewport) {
                            bounds.union(place.geometry.viewport);
                        } else {
                            bounds.extend(place.geometry.location);
                        }
                    });
                    map.fitBounds(bounds);
                });
            };
            initAutocomplete();
        });

        function deliveryAdressStore(form_id = 'delivery_address_store') {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('admin.pos.add-delivery-address') }}',
                data: $('#' + form_id).serializeArray(),
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    if (data.errors) {
                        for (var i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else {
                        $('#del-add').empty().html(data.view);
                    }
                    updateCart();
                    $('.call-when-done').click();
                },
                complete: function() {
                    $('#loading').hide();
                }
            });
        }

        $(document).on('ready', function () {
            $('.js-select2-custom-x').each(function () {
                var select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });

        $(document).ready(function() {
            const $areaDropdown = $('#areaDropdown');
            const $deliveryChargeInput = $('#deliveryChargeInput');

            $areaDropdown.change(function() {
                const selectedOption = $(this).find('option:selected');
                const charge = selectedOption.data('charge');
                $deliveryChargeInput.val(charge);
            });
        });

        $(document).ready(function(){
            function initCouponSlider() {
                const $container = $('.coupon-inner');
                const $btnPrevWrap = $('.button-prev');
                const $btnNextWrap = $('.button-next');
                const $prevBtn = $('.btn-click-prev');
                const $nextBtn = $('.btn-click-next');
                const $item = $('.coupon-slide_items').first();

                if (!$container.length) return;
                const show = $el => $el.css('display', 'flex');
                const hide = $el => $el.css('display', 'none');

                hide($btnPrevWrap);
                hide($btnNextWrap);
                function updateArrows() {
                    if (!$container[0]) return;
                    const scrollLeft = Math.ceil($container.scrollLeft());
                    const clientWidth = $container[0].clientWidth;
                    const scrollWidth = $container[0].scrollWidth;
                    const maxScroll = Math.max(0, scrollWidth - clientWidth);

                    if (maxScroll <= 0) {
                        hide($btnPrevWrap);
                        hide($btnNextWrap);
                        return;
                    }

                    if (scrollLeft > 0) show($btnPrevWrap);
                    else hide($btnPrevWrap);

                    if (scrollLeft < maxScroll - 1) show($btnNextWrap);
                    else hide($btnNextWrap);
                }
                function getItemWidth() {
                    if ($item.length) return $item.outerWidth() || 0;
                    return Math.round($container.innerWidth() * 0.48);
                }
                $prevBtn.off('click').on('click', function () {
                    const w = getItemWidth();
                    const target = Math.max(0, $container.scrollLeft() - w);
                    $container.animate({ scrollLeft: target }, 300, updateArrows);
                });

                $nextBtn.off('click').on('click', function () {
                    const w = getItemWidth();
                    const max = Math.max(0, $container[0].scrollWidth - $container.innerWidth());
                    const target = Math.min(max, $container.scrollLeft() + w);
                    $container.animate({ scrollLeft: target }, 300, updateArrows);
                });
                $container.on('scroll', updateArrows);
                let resizeTimer;
                $(window).off('resize.coupon').on('resize.coupon', () => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(updateArrows, 80);
                });

                try {
                    const mo = new MutationObserver(() => {
                        clearTimeout(resizeTimer);
                        resizeTimer = setTimeout(updateArrows, 80);
                    });
                    mo.observe($container[0], { childList: true, subtree: true });
                } catch (e) {  }

                try {
                    const ro = new ResizeObserver(() => {
                        clearTimeout(resizeTimer);
                        resizeTimer = setTimeout(updateArrows, 80);
                    });
                    ro.observe($container[0]);
                } catch (e) {  }

                requestAnimationFrame(() => requestAnimationFrame(updateArrows));
            }

            initCouponSlider();

        })

        $(document).on('click', '.coupon-card', function () {
            let code = $(this).data('code');
            $('#couponInput').val(code);
        });

        $(document).on('click', '.open-coupon-modal', function () {

            let totalAmount = parseFloat($('#hiddenProductAndAddonPriceAfterDiscount').val()) || 0;

            $("#dynamic-coupon-list").html(`
                <div class="text-center w-100 py-3">
                    Loading coupons...
                </div>
            `);

            // Call the backend route
            $.get("{{ route('admin.pos.coupon.list') }}", {
                amount: totalAmount
            }, function (response) {
                $("#dynamic-coupon-list").html(response.html);
            });
        });

        $(document).on('click', '.remove-session-coupon', function () {
            $('#confirm-remove-coupon').modal('show');
        });

        $(document).on('click', '#confirm-remove-btn', function () {
            $.ajax({
                url: '{{ route("admin.pos.coupon.remove") }}',
                type: 'GET',
                success: function(data) {
                    $('#confirm-remove-coupon').modal('hide');

                    if (data.status) {
                        toastr.success('Coupon removed successfully');
                        location.reload();
                    } else {
                        toastr.error('Coupon remove failed');
                    }
                }
            });
        });

        $(document).on('click', '.empty-cart-button', function (){
            emptyCart();
        })

    </script>

    <script>
        if (/MSIE \d|Trident.*rv:/.test(navigator.userAgent)) document.write('<script src="{{asset('public/assets/admin')}}/vendor/babel-polyfill/polyfill.min.js"><\/script>');
    </script>

    @if(session('order_placed'))
    <script>
        (function () {
            'use strict';
            var data = {!! json_encode(session('order_placed')) !!};

            // 1) Audio chime — synthesised via Web Audio API (no asset file needed).
            try {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (AC) {
                    var ctx = new AC();
                    function beep(freq, startMs, durMs) {
                        var o = ctx.createOscillator(), g = ctx.createGain();
                        o.type = 'sine'; o.frequency.value = freq;
                        g.gain.setValueAtTime(0, ctx.currentTime + startMs/1000);
                        g.gain.linearRampToValueAtTime(0.35, ctx.currentTime + startMs/1000 + 0.02);
                        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (startMs+durMs)/1000);
                        o.connect(g); g.connect(ctx.destination);
                        o.start(ctx.currentTime + startMs/1000);
                        o.stop (ctx.currentTime + (startMs+durMs)/1000 + 0.05);
                    }
                    // Cheerful two-tone ding
                    beep(880, 0,   180);
                    beep(1320, 180, 260);
                }
            } catch (e) { /* audio not available; fine */ }

            // 2) Modal
            var currency = '{{ \App\CentralLogics\Helpers::currency_symbol() }}';
            $('#op-order-id').text('#' + data.id);
            $('#op-amount').text(currency + Number(data.amount || 0).toFixed(2));
            if (data.table) {
                $('#op-table').text(data.table + (data.zone ? ' · ' + data.zone : ''));
                $('#op-table-line').show();
            }
            $('#op-send-kitchen').off('click').on('click', function () {
                var url = '{{ url('admin/orders') }}/' + data.id + '/kitchen-ticket';
                window.open(url, '_blank');
                $('#orderPlacedModal').modal('hide');
            });
            $('#orderPlacedModal').modal({ backdrop: 'static', keyboard: true, show: true });
        })();
    </script>
    @endif

    {{-- POS keyboard shortcuts --}}
    <script>
    (function () {
        'use strict';

        const isEditable = (el) => {
            if (!el) return false;
            const tag = el.tagName;
            return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
                || el.isContentEditable
                || (el.getAttribute && el.getAttribute('role') === 'textbox');
        };

        const focusSearch = () => {
            const el = document.getElementById('datatableSearch');
            if (el) { el.focus(); el.select(); }
        };

        const pickCategoryAtIndex = (idx) => {
            const sel = document.querySelector('select.category');
            if (!sel) return false;
            const opts = sel.options;
            if (idx < 0 || idx >= opts.length) return false;
            sel.value = opts[idx].value;
            const ev = new Event('change', { bubbles: true });
            sel.dispatchEvent(ev);
            return true;
        };

        const submitOrder = () => {
            const form = document.getElementById('order_place');
            if (form) { form.requestSubmit ? form.requestSubmit() : form.submit(); return true; }
            return false;
        };

        const toggleHelp = () => {
            const o = document.getElementById('pos-shortcuts-help');
            if (o) o.hidden = !o.hidden;
        };

        document.addEventListener('keydown', function (e) {
            // "?" shows help from anywhere (Shift+/ on US layouts).
            if (e.key === '?' && !isEditable(document.activeElement)) {
                e.preventDefault(); toggleHelp(); return;
            }

            if (isEditable(document.activeElement)) {
                // Only Esc escapes input focus; Cmd/Ctrl+Enter submits from any input.
                if (e.key === 'Escape') {
                    document.activeElement.blur();
                }
                if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
                    e.preventDefault();
                    submitOrder();
                }
                return;
            }

            // Global POS shortcuts (only when NOT typing)
            if (e.key === '/')            { e.preventDefault(); focusSearch(); return; }
            // Esc: close help overlay if it's open; otherwise let the event
            // propagate so Bootstrap can dismiss any open modal.
            if (e.key === 'Escape') {
                const helpEl = document.getElementById('pos-shortcuts-help');
                if (helpEl && !helpEl.hidden) { helpEl.hidden = true; e.preventDefault(); }
                return;
            }
            if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
                e.preventDefault(); submitOrder(); return;
            }

            // 1-9 → Nth category, 0 → All categories
            if (/^[0-9]$/.test(e.key) && !e.metaKey && !e.ctrlKey && !e.altKey) {
                const n = Number(e.key);
                const ok = pickCategoryAtIndex(n);   // 0 = "All", 1..N = categories
                if (ok) e.preventDefault();
            }
        });

        // Expose help-open handler on the hint badge next to the search input.
        document.addEventListener('click', function (e) {
            if (e.target.closest('#pos-shortcut-hint')) toggleHelp();
            if (e.target.id === 'pos-shortcuts-help')   toggleHelp();
            if (e.target.closest('#pos-shortcuts-help-close')) toggleHelp();
        });
    })();
    </script>

    {{-- Tiny visual hint inside the search input --}}
    <style>
        .pos-search-hint {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            font-size: 10px; color: #8e8e93;
            background: #f2f2f7; border: 1px solid #d8d8dd; border-radius: 4px;
            padding: 1px 6px; pointer-events: auto; cursor: pointer;
            user-select: none;
        }
        #datatableSearch { padding-right: 40px; }
        #datatableSearch + .pos-search-hint { pointer-events: auto; }
    </style>
    <script>
        (function () {
            const input = document.getElementById('datatableSearch');
            if (!input) return;
            // Ensure the input's parent is positioned so the hint anchors correctly.
            const wrap = input.closest('.input-group') || input.parentElement;
            if (wrap) wrap.style.position = 'relative';
            if (!document.getElementById('pos-shortcut-hint')) {
                const hint = document.createElement('span');
                hint.id = 'pos-shortcut-hint';
                hint.className = 'pos-search-hint';
                hint.title = 'Press / to focus. Press ? for shortcuts.';
                hint.innerHTML = '<kbd style="background:#fff;border:1px solid #d8d8dd;border-radius:3px;padding:0 4px;font-size:10px;">/</kbd>';
                (wrap || input.parentElement).appendChild(hint);
            }
        })();
    </script>

    {{-- Shortcut help overlay --}}
    <div id="pos-shortcuts-help" hidden
         style="position:fixed;inset:0;z-index:10400;background:rgba(20,20,30,0.55);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Inter,sans-serif;">
        <div style="width:100%;max-width:460px;background:#fff;color:#1a1a1a;border-radius:14px;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #e5e5ea;display:flex;align-items:center;gap:8px;">
                <strong style="flex:1;font-size:15px;">Keyboard shortcuts</strong>
                <button type="button" id="pos-shortcuts-help-close" aria-label="Close"
                        style="background:transparent;border:0;font-size:22px;line-height:1;color:#8e8e93;cursor:pointer;">&times;</button>
            </div>
            <ul style="list-style:none;margin:0;padding:10px 0;font-size:14px;">
                <?php
                    $kbd = 'display:inline-block;padding:1px 7px;background:#f2f2f7;border:1px solid #d8d8dd;border-radius:4px;font-family:inherit;font-size:11px;font-weight:600;color:#444;box-shadow:0 1px 0 #d8d8dd;';
                    $row = 'display:flex;align-items:center;gap:12px;padding:8px 20px;';
                ?>
                <li style="{{$row}}"><span style="flex:1;">Focus search</span><kbd style="{{$kbd}}">/</kbd></li>
                <li style="{{$row}}"><span style="flex:1;">Select category 1–9</span><kbd style="{{$kbd}}">1</kbd> – <kbd style="{{$kbd}}">9</kbd></li>
                <li style="{{$row}}"><span style="flex:1;">All categories</span><kbd style="{{$kbd}}">0</kbd></li>
                <li style="{{$row}}"><span style="flex:1;">Place order</span><kbd style="{{$kbd}}">⌘</kbd><kbd style="{{$kbd}}">↵</kbd></li>
                <li style="{{$row}}"><span style="flex:1;">Clear focus / close</span><kbd style="{{$kbd}}">Esc</kbd></li>
                <li style="{{$row}}"><span style="flex:1;">Show this help</span><kbd style="{{$kbd}}">?</kbd></li>
                <li style="{{$row}}"><span style="flex:1;">Open command palette</span><kbd style="{{$kbd}}">⌘</kbd><kbd style="{{$kbd}}">K</kbd></li>
            </ul>
            <div style="padding:10px 20px;background:#f7f7fa;border-top:1px solid #e5e5ea;font-size:11px;color:#6a6a70;">
                Shortcuts work only when you're not typing in a field.
            </div>
        </div>
    </div>
@endpush

