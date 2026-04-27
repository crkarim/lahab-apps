@extends('layouts.branch.app')

@section('title', translate('Dashboard'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin') }}/vendor/apex/apexcharts.css">
    <style>
        /* Branch dashboard — mirrors the admin dashboard band-for-band but
           every query (and therefore every number on screen) is scoped to
           the authenticated branch. Class names share the `lh-d-` prefix
           with the admin view so future style tweaks stay in sync. */
        .lh-d-page { background: #f6f7fa; min-height: calc(100vh - 60px); padding: 18px 0 40px; }
        .lh-d-shell { max-width: 1320px; margin: 0 auto; padding: 0 16px; }

        .lh-d-greet { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .lh-d-greet h1 { font-size: 22px; font-weight: 700; color: #1a1a1a; margin: 0; }
        .lh-d-greet .lh-d-sub { color: #8e8e93; font-size: 13px; margin-top: 2px; }
        .lh-d-greet .lh-d-today-pill {
            background: #fff; border: 1px solid #eceef0; border-radius: 999px;
            padding: 6px 14px; font-size: 12px; color: #555; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .lh-d-greet .lh-d-today-pill i { color: #E67E22; }

        .lh-d-section-label {
            font-size: 11px; letter-spacing: 1.2px; text-transform: uppercase;
            color: #8e8e93; font-weight: 700; margin: 22px 0 10px;
            display: flex; align-items: center; justify-content: space-between;
        }

        .lh-d-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .lh-d-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        .lh-d-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
        @media (max-width: 991px) { .lh-d-grid-4, .lh-d-grid-3 { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 575px) { .lh-d-grid-4, .lh-d-grid-3, .lh-d-grid-2 { grid-template-columns: 1fr; } }

        .lh-d-tile {
            background: #fff; border: 1px solid #eceef0; border-radius: 14px;
            padding: 18px 20px; position: relative; overflow: hidden;
            transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
            text-decoration: none; color: inherit; display: block;
        }
        .lh-d-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.05); border-color: #E67E22; text-decoration: none; color: inherit; }
        .lh-d-tile-label { font-size: 11px; letter-spacing: 0.6px; text-transform: uppercase; color: #8e8e93; margin-bottom: 8px; font-weight: 700; }
        .lh-d-tile-value { font-size: 28px; font-weight: 800; line-height: 1.05; color: #1a1a1a; }
        .lh-d-tile-unit  { font-size: 14px; font-weight: 600; color: #8e8e93; margin-left: 4px; }
        .lh-d-tile-meta  { font-size: 12px; color: #8e8e93; margin-top: 6px; }
        .lh-d-tile-delta { font-size: 12px; font-weight: 700; margin-top: 8px; display: inline-flex; align-items: center; gap: 4px; }
        .lh-d-tile-delta.up   { color: #28a745; }
        .lh-d-tile-delta.down { color: #dc3545; }
        .lh-d-tile-delta.flat { color: #8e8e93; }
        .lh-d-tile-ico {
            position: absolute; top: 16px; right: 16px;
            width: 36px; height: 36px; border-radius: 11px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
        .lh-d-tile.lh-accent-orange { border-top: 3px solid #E67E22; }
        .lh-d-tile.lh-accent-orange .lh-d-tile-ico { background: rgba(230,126,34,0.12); color: #E67E22; }
        .lh-d-tile.lh-accent-blue   { border-top: 3px solid #4a90e2; }
        .lh-d-tile.lh-accent-blue   .lh-d-tile-ico { background: rgba(74,144,226,0.12); color: #4a90e2; }
        .lh-d-tile.lh-accent-green  { border-top: 3px solid #28a745; }
        .lh-d-tile.lh-accent-green  .lh-d-tile-ico { background: rgba(40,167,69,0.12); color: #28a745; }
        .lh-d-tile.lh-accent-purple { border-top: 3px solid #8e44ad; }
        .lh-d-tile.lh-accent-purple .lh-d-tile-ico { background: rgba(142,68,173,0.12); color: #8e44ad; }
        .lh-d-tile.lh-accent-amber  { border-top: 3px solid #f0a030; }
        .lh-d-tile.lh-accent-amber  .lh-d-tile-ico { background: rgba(240,160,48,0.14); color: #f0a030; }
        .lh-d-tile.lh-accent-red    { border-top: 3px solid #dc3545; }
        .lh-d-tile.lh-accent-red    .lh-d-tile-ico { background: rgba(220,53,69,0.12); color: #dc3545; }

        .lh-d-funnel-tile {
            background: #fff; border: 1px solid #eceef0; border-radius: 14px;
            padding: 16px 18px; text-decoration: none; color: inherit; display: block;
            transition: transform 120ms ease, box-shadow 120ms ease, border-color 120ms ease;
        }
        .lh-d-funnel-tile:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(230,126,34,0.10); border-color: #E67E22; text-decoration: none; color: inherit; }
        .lh-d-funnel-row { display: flex; align-items: center; gap: 12px; }
        .lh-d-funnel-num { font-size: 30px; font-weight: 800; line-height: 1; color: #1a1a1a; min-width: 44px; }
        .lh-d-funnel-meta { flex: 1; min-width: 0; }
        .lh-d-funnel-label { font-size: 13px; font-weight: 700; color: #1a1a1a; }
        .lh-d-funnel-hint  { font-size: 11px; color: #8e8e93; margin-top: 1px; }
        .lh-d-funnel-tile.is-active .lh-d-funnel-num { color: #E67E22; }
        .lh-d-funnel-tile.is-zero .lh-d-funnel-num   { color: #c0c0c0; }

        .lh-d-card {
            background: #fff; border: 1px solid #eceef0; border-radius: 14px;
            padding: 20px 22px;
        }
        .lh-d-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; gap: 10px; flex-wrap: wrap; }
        .lh-d-card-title { font-size: 14px; font-weight: 700; color: #1a1a1a; margin: 0; display: flex; align-items: center; gap: 8px; }
        .lh-d-card-title i { color: #E67E22; }

        .lh-d-range { display: inline-flex; background: #f6f7fa; border-radius: 999px; padding: 3px; }
        .lh-d-range button {
            border: 0; background: transparent; padding: 5px 14px;
            font-size: 12px; font-weight: 700; color: #8e8e93;
            border-radius: 999px; cursor: pointer; transition: background 120ms;
        }
        .lh-d-range button.is-active { background: #E67E22; color: #fff; }

        .lh-d-list { list-style: none; padding: 0; margin: 0; }
        .lh-d-list li {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 0; border-bottom: 1px solid #f1f2f4;
        }
        .lh-d-list li:last-child { border-bottom: 0; }
        .lh-d-list .lh-d-rank {
            width: 24px; height: 24px; border-radius: 8px;
            background: #f6f7fa; color: #8e8e93;
            font-size: 12px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .lh-d-list li:nth-child(1) .lh-d-rank { background: rgba(230,126,34,0.14); color: #E67E22; }
        .lh-d-list .lh-d-thumb {
            width: 36px; height: 36px; border-radius: 9px; object-fit: cover; background: #f6f7fa;
        }
        .lh-d-list .lh-d-name { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; color: #1a1a1a;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lh-d-list .lh-d-qty  { font-size: 12px; color: #8e8e93; font-weight: 600; min-width: 36px; text-align: right; }
        .lh-d-list .lh-d-rev  { font-size: 13px; font-weight: 700; color: #1a1a1a; min-width: 64px; text-align: right; }

        .lh-d-order-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f2f4; }
        .lh-d-order-row:last-child { border-bottom: 0; }
        .lh-d-order-id { font-size: 13px; font-weight: 700; color: #1a1a1a; min-width: 60px; }
        .lh-d-order-meta { flex: 1; min-width: 0; }
        .lh-d-order-who  { font-size: 13px; color: #1a1a1a; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lh-d-order-when { font-size: 11px; color: #8e8e93; margin-top: 1px; }
        .lh-d-order-amt  { font-size: 14px; font-weight: 700; color: #1a1a1a; }

        .lh-d-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .lh-d-pill.t-dine_in   { background: rgba(74,144,226,0.12); color: #4a90e2; }
        .lh-d-pill.t-pos       { background: rgba(230,126,34,0.12); color: #E67E22; }
        .lh-d-pill.t-delivery  { background: rgba(142,68,173,0.12); color: #8e44ad; }

        .lh-d-empty { text-align: center; padding: 30px 10px; color: #b0b3b8; font-size: 13px; }
        .lh-d-empty i { font-size: 32px; display: block; margin-bottom: 6px; opacity: 0.6; }

        #lh-d-trend-chart { min-height: 260px; }
    </style>
@endpush

@section('content')
    <div class="lh-d-page">
        <div class="lh-d-shell">

            {{-- ── Greeting ───────────────────────────────────────────── --}}
            <div class="lh-d-greet">
                <div>
                    <h1>{{ translate('welcome') }}, {{ auth('branch')->user()->name }}.</h1>
                    <div class="lh-d-sub">{{ translate('Here_is_what_is_happening_at_your_branch_today') }}</div>
                </div>
                <span class="lh-d-today-pill">
                    <i class="tio-date-range"></i> {{ \Carbon\Carbon::today()->format('l, d M Y') }}
                </span>
            </div>

            {{-- ── 1. Today snapshot ─────────────────────────────────── --}}
            <div class="lh-d-section-label">{{ translate('Today') }}</div>
            <div class="lh-d-grid-4">
                @php
                    $deltaCls = fn ($p) => $p > 0 ? 'up' : ($p < 0 ? 'down' : 'flat');
                    $deltaIco = fn ($p) => $p > 0 ? 'tio-trending-up' : ($p < 0 ? 'tio-trending-down' : 'tio-remove');
                @endphp

                <div class="lh-d-tile lh-accent-orange">
                    <div class="lh-d-tile-ico"><i class="tio-money"></i></div>
                    <div class="lh-d-tile-label">{{ translate('Revenue') }}</div>
                    <div class="lh-d-tile-value">{{ \App\CentralLogics\Helpers::set_symbol($snapshot['revenue']) }}</div>
                    <div class="lh-d-tile-delta {{ $deltaCls($snapshot['revenue_delta_pct']) }}">
                        <i class="{{ $deltaIco($snapshot['revenue_delta_pct']) }}"></i>
                        {{ $snapshot['revenue_delta_pct'] }}% {{ translate('vs_yesterday') }}
                    </div>
                </div>

                <div class="lh-d-tile lh-accent-blue">
                    <div class="lh-d-tile-ico"><i class="tio-shopping-cart"></i></div>
                    <div class="lh-d-tile-label">{{ translate('Orders') }}</div>
                    <div class="lh-d-tile-value">{{ $snapshot['orders'] }}</div>
                    <div class="lh-d-tile-delta {{ $deltaCls($snapshot['orders_delta_pct']) }}">
                        <i class="{{ $deltaIco($snapshot['orders_delta_pct']) }}"></i>
                        {{ $snapshot['orders_delta_pct'] }}% {{ translate('vs_yesterday') }}
                    </div>
                </div>

                <div class="lh-d-tile lh-accent-purple">
                    <div class="lh-d-tile-ico"><i class="tio-chart-bar-2"></i></div>
                    <div class="lh-d-tile-label">{{ translate('Avg_order_value') }}</div>
                    <div class="lh-d-tile-value">{{ \App\CentralLogics\Helpers::set_symbol($snapshot['aov']) }}</div>
                    <div class="lh-d-tile-delta {{ $deltaCls($snapshot['aov_delta_pct']) }}">
                        <i class="{{ $deltaIco($snapshot['aov_delta_pct']) }}"></i>
                        {{ $snapshot['aov_delta_pct'] }}% {{ translate('vs_yesterday') }}
                    </div>
                </div>

                <div class="lh-d-tile lh-accent-green">
                    <div class="lh-d-tile-ico"><i class="tio-restaurant"></i></div>
                    <div class="lh-d-tile-label">{{ translate('Tables_in_use') }}</div>
                    <div class="lh-d-tile-value">
                        {{ $snapshot['tables_in_use'] }}<span class="lh-d-tile-unit">/ {{ $snapshot['tables_total'] }}</span>
                    </div>
                    <div class="lh-d-tile-meta">
                        @php $occ = $snapshot['tables_total'] > 0 ? round(($snapshot['tables_in_use'] / $snapshot['tables_total']) * 100) : 0; @endphp
                        {{ $occ }}% {{ translate('occupancy_right_now') }}
                    </div>
                </div>
            </div>

            {{-- ── 2. Live operations ───────────────────────────────── --}}
            <div class="lh-d-section-label">
                {{ translate('Live_operations') }}
                <a href="{{ route('branch.orders.list', ['status' => 'all']) }}" style="font-size: 11px; color: #E67E22; text-decoration: none; font-weight: 700; letter-spacing: 0.5px;">
                    {{ translate('Open_active_orders') }} <i class="tio-chevron-right"></i>
                </a>
            </div>
            <div class="lh-d-grid-4">
                @php
                    $funnelTiles = [
                        ['key' => 'pending_kitchen', 'label' => translate('Pending_kitchen'),  'hint' => translate('not_yet_fired'),       'icon' => 'tio-time'],
                        ['key' => 'in_kitchen',      'label' => translate('In_kitchen'),       'hint' => translate('cooking_now'),         'icon' => 'tio-fastfood'],
                        ['key' => 'on_route',        'label' => translate('On_route'),         'hint' => translate('out_for_delivery'),    'icon' => 'tio-bike'],
                        ['key' => 'awaiting_payment','label' => translate('Awaiting_payment'),'hint' => translate('served_not_paid'),     'icon' => 'tio-credit-card-outlined'],
                    ];
                @endphp
                @foreach($funnelTiles as $t)
                    <a href="{{ route('branch.orders.list', ['status' => 'all']) }}"
                       class="lh-d-funnel-tile {{ $live[$t['key']] > 0 ? 'is-active' : 'is-zero' }}">
                        <div class="lh-d-funnel-row">
                            <div class="lh-d-funnel-num">{{ $live[$t['key']] }}</div>
                            <div class="lh-d-funnel-meta">
                                <div class="lh-d-funnel-label"><i class="{{ $t['icon'] }}"></i> {{ $t['label'] }}</div>
                                <div class="lh-d-funnel-hint">{{ $t['hint'] }}</div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- ── 3. Revenue trend ─────────────────────────────────── --}}
            <div class="lh-d-section-label">{{ translate('Revenue_trend') }}</div>
            <div class="lh-d-card">
                <div class="lh-d-card-head">
                    <h2 class="lh-d-card-title"><i class="tio-poll"></i> {{ translate('Revenue_and_orders') }}</h2>
                    <div class="lh-d-range" id="lh-d-range">
                        <button data-range="7d">{{ translate('7_days') }}</button>
                        <button data-range="30d" class="is-active">{{ translate('30_days') }}</button>
                        <button data-range="12m">{{ translate('12_months') }}</button>
                    </div>
                </div>
                <div id="lh-d-trend-chart"></div>
            </div>

            {{-- ── 4. What's moving ─────────────────────────────────── --}}
            <div class="lh-d-section-label">{{ translate('What_is_moving') }}</div>
            <div class="lh-d-grid-2">
                <div class="lh-d-card">
                    <div class="lh-d-card-head">
                        <h2 class="lh-d-card-title"><i class="tio-fire-on"></i> {{ translate('Top_dishes_today') }}</h2>
                    </div>
                    @if($topToday->isEmpty())
                        <div class="lh-d-empty">
                            <i class="tio-restaurant"></i>
                            {{ translate('No_dishes_sold_yet_today') }}
                        </div>
                    @else
                        <ul class="lh-d-list">
                            @foreach($topToday as $i => $d)
                                <li>
                                    <span class="lh-d-rank">{{ $i + 1 }}</span>
                                    @if($d->product?->image)
                                        <img class="lh-d-thumb" src="{{ asset('storage/app/public/product/' . $d->product->image) }}"
                                             onerror="this.style.visibility='hidden'" alt="">
                                    @else
                                        <span class="lh-d-thumb"></span>
                                    @endif
                                    <span class="lh-d-name">{{ $d->product?->name ?? translate('Removed_product') }}</span>
                                    <span class="lh-d-qty">{{ $d->qty }}×</span>
                                    <span class="lh-d-rev">{{ \App\CentralLogics\Helpers::set_symbol($d->revenue) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="lh-d-card">
                    <div class="lh-d-card-head">
                        <h2 class="lh-d-card-title"><i class="tio-time"></i> {{ translate('Recent_orders') }}</h2>
                        <a href="{{ route('branch.orders.list', ['status' => 'all']) }}" style="font-size: 11px; color: #E67E22; font-weight: 700; text-decoration: none;">
                            {{ translate('See_all') }} <i class="tio-chevron-right"></i>
                        </a>
                    </div>
                    @if($recent->isEmpty())
                        <div class="lh-d-empty">
                            <i class="tio-shopping-cart"></i>
                            {{ translate('No_orders_yet') }}
                        </div>
                    @else
                        @foreach($recent as $o)
                            <div class="lh-d-order-row">
                                <span class="lh-d-order-id">#{{ $o->id }}</span>
                                <div class="lh-d-order-meta">
                                    <div class="lh-d-order-who">
                                        <span class="lh-d-pill t-{{ $o->order_type }}">{{ str_replace('_', ' ', $o->order_type) }}</span>
                                        @if($o->customer)
                                            {{ $o->customer->f_name }} {{ $o->customer->l_name }}
                                        @elseif($o->table)
                                            {{ translate('Table') }} {{ $o->table->number }}
                                        @else
                                            {{ translate('Walk_in') }}
                                        @endif
                                    </div>
                                    <div class="lh-d-order-when">{{ $o->created_at->diffForHumans() }}</div>
                                </div>
                                <span class="lh-d-order-amt">{{ \App\CentralLogics\Helpers::set_symbol($o->order_amount) }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- ── 5. Operational alerts (placeholder cards, live data) ── --}}
            <div class="lh-d-section-label">{{ translate('Alerts') }}</div>
            <div class="lh-d-grid-3">
                <a href="{{ route('branch.product.list') }}" class="lh-d-tile lh-accent-amber">
                    <div class="lh-d-tile-ico"><i class="tio-warning"></i></div>
                    <div class="lh-d-tile-label">{{ translate('Low_stock_items') }}</div>
                    <div class="lh-d-tile-value">{{ $alerts['low_stock'] }}</div>
                    <div class="lh-d-tile-meta">{{ translate('products_at_or_below_5_units') }}</div>
                </a>

                <a href="{{ route('branch.orders.list', ['status' => 'returned']) }}" class="lh-d-tile lh-accent-red">
                    <div class="lh-d-tile-ico"><i class="tio-undo"></i></div>
                    <div class="lh-d-tile-label">{{ translate('Pending_refunds') }}</div>
                    <div class="lh-d-tile-value">{{ $alerts['pending_refunds'] }}</div>
                    <div class="lh-d-tile-meta">{{ translate('returned_or_balance_owed') }}</div>
                </a>

                {{-- Branch panel has no employee-management page, so the staff
                     card is informational only (no link). --}}
                <div class="lh-d-tile lh-accent-green">
                    <div class="lh-d-tile-ico"><i class="tio-group-equal"></i></div>
                    <div class="lh-d-tile-label">{{ translate('Staff_on_shift_today') }}</div>
                    <div class="lh-d-tile-value">{{ $alerts['staff_on_shift'] }}</div>
                    <div class="lh-d-tile-meta">{{ translate('rang_at_least_one_order_today') }}</div>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/vendor/apex/apexcharts.min.js"></script>
    <script>
        (function () {
            const trend = @json($trend);
            let currentRange = '30d';

            function buildOptions(range) {
                const d = trend[range];
                return {
                    chart: {
                        type: 'area', height: 280, toolbar: { show: false },
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                        animations: { enabled: true, speed: 350 }
                    },
                    series: [
                        { name: '{{ translate('Revenue') }}', type: 'area', data: d.revenue },
                        { name: '{{ translate('Orders') }}',  type: 'line', data: d.orders }
                    ],
                    colors: ['#E67E22', '#4a90e2'],
                    stroke: { curve: 'smooth', width: [2, 2] },
                    fill: {
                        type: ['gradient', 'solid'],
                        gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.25, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 100] }
                    },
                    dataLabels: { enabled: false },
                    xaxis: {
                        categories: d.labels,
                        labels: { style: { colors: '#8e8e93', fontSize: '11px' } },
                        axisBorder: { show: false }, axisTicks: { show: false }
                    },
                    yaxis: [
                        { seriesName: '{{ translate('Revenue') }}',
                          labels: { style: { colors: '#8e8e93', fontSize: '11px' },
                                    formatter: (v) => v >= 1000 ? (v / 1000).toFixed(1) + 'k' : Math.round(v) } },
                        { seriesName: '{{ translate('Orders') }}', opposite: true,
                          labels: { style: { colors: '#8e8e93', fontSize: '11px' }, formatter: (v) => Math.round(v) } }
                    ],
                    grid: { borderColor: '#f1f2f4', strokeDashArray: 4 },
                    legend: { position: 'top', horizontalAlign: 'right', markers: { width: 8, height: 8, radius: 4 }, fontSize: '12px' },
                    tooltip: { theme: 'light', shared: true }
                };
            }

            const chart = new ApexCharts(document.querySelector('#lh-d-trend-chart'), buildOptions('30d'));
            chart.render();

            document.querySelectorAll('#lh-d-range button').forEach((btn) => {
                btn.addEventListener('click', function () {
                    const range = this.dataset.range;
                    if (range === currentRange) return;
                    currentRange = range;
                    document.querySelectorAll('#lh-d-range button').forEach(b => b.classList.toggle('is-active', b === this));
                    chart.updateOptions(buildOptions(range), false, true);
                });
            });
        })();
    </script>
@endpush
