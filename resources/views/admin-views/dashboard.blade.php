@extends('layouts.admin.app')

@section('title', translate('Dashboard'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" src="{{asset('public/assets/admin')}}/vendor/apex/apexcharts.css"></link>
@endpush

@section('content')
        <div class="content container-fluid">
            <div>
                <div class="row align-items-center">
                    <div class="col-sm mb-2 mb-sm-0">
                        <h1 class="page-header-title c1">{{translate('welcome')}}, {{auth('admin')->user()->f_name}}.</h1>
                        <p class="text-dark font-weight-semibold">{{translate('Monitor_your_business_analytics_and_statistics')}}</p>
                    </div>
                </div>
            </div>

            {{-- ───────────────────────────── Today's Snapshot ───────────────────────────── --}}
            <?php
                $todayStart    = \Carbon\Carbon::today();
                $yestStart     = \Carbon\Carbon::yesterday();
                $ordersToday   = \App\Model\Order::whereDate('created_at', $todayStart)->count();
                $ordersYest    = \App\Model\Order::whereDate('created_at', $yestStart)->count();
                $revenueToday  = (float) \App\Model\Order::whereDate('created_at', $todayStart)
                                    ->where('order_status', '!=', 'canceled')
                                    ->sum('order_amount');
                $revenueYest   = (float) \App\Model\Order::whereDate('created_at', $yestStart)
                                    ->where('order_status', '!=', 'canceled')
                                    ->sum('order_amount');
                $aovToday      = $ordersToday > 0 ? ($revenueToday / $ordersToday) : 0;
                $runningTables = \App\Model\Order::where('order_type', 'dine_in')
                                    ->where('payment_status', '!=', 'paid')
                                    ->whereNotIn('order_status', ['completed', 'canceled', 'failed', 'refunded'])
                                    ->count();
                $pendingKOT    = \App\Model\Order::whereIn('order_type', ['pos', 'dine_in'])
                                    ->where('order_status', 'confirmed')
                                    ->whereNull('kot_sent_at')
                                    ->count();
                $pctChange = function ($now, $prev) {
                    if ($prev == 0) return $now > 0 ? 100 : 0;
                    return round((($now - $prev) / $prev) * 100);
                };
                $ordersPct  = $pctChange($ordersToday,  $ordersYest);
                $revenuePct = $pctChange($revenueToday, $revenueYest);
            ?>

            <style>
                .lh-hero { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 14px; }
                @media (max-width: 991px) { .lh-hero { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 575px) { .lh-hero { grid-template-columns: 1fr; } }
                .lh-tile {
                    background: #fff; border: 1px solid #eceef0; border-radius: 12px;
                    padding: 16px 18px; position: relative; overflow: hidden;
                    transition: transform 120ms ease, box-shadow 120ms ease;
                }
                .lh-tile:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(0,0,0,0.04); }
                .lh-tile-label { font-size: 11px; letter-spacing: 0.6px; text-transform: uppercase; color: #8e8e93; margin-bottom: 6px; font-weight: 600; }
                .lh-tile-value { font-size: 26px; font-weight: 700; line-height: 1.1; color: #1a1a1a; }
                .lh-tile-unit  { font-size: 14px; font-weight: 600; color: #8e8e93; margin-left: 3px; }
                .lh-tile-delta { font-size: 11px; font-weight: 600; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; }
                .lh-tile-delta.up   { color: #28a745; }
                .lh-tile-delta.down { color: #dc3545; }
                .lh-tile-delta.flat { color: #8e8e93; }
                .lh-tile-ico {
                    position: absolute; top: 12px; right: 14px;
                    width: 34px; height: 34px; border-radius: 10px;
                    display: inline-flex; align-items: center; justify-content: center;
                    font-size: 18px; background: #f5f6f8;
                }
                .lh-tile.accent-orange .lh-tile-ico { background: rgba(252,106,87,0.12); }
                .lh-tile.accent-green  .lh-tile-ico { background: rgba(40,167,69,0.12); }
                .lh-tile.accent-blue   .lh-tile-ico { background: rgba(74,144,226,0.12); }
                .lh-tile.accent-purple .lh-tile-ico { background: rgba(142,68,173,0.12); }

                .lh-quick { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
                @media (max-width: 767px) { .lh-quick { grid-template-columns: repeat(2, 1fr); } }
                .lh-quick a {
                    display: flex; align-items: center; gap: 10px;
                    padding: 12px 14px; background: #fff; border: 1px solid #eceef0;
                    border-radius: 12px; color: #1a1a1a; text-decoration: none;
                    font-weight: 500; font-size: 14px;
                    transition: border-color 120ms, transform 120ms, box-shadow 120ms;
                }
                .lh-quick a:hover { border-color: #E67E22; color: #E67E22; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(230,126,34,0.10); }
                .lh-quick-ico {
                    width: 28px; height: 28px; border-radius: 8px;
                    display: inline-flex; align-items: center; justify-content: center;
                    background: #f5f6f8; font-size: 14px;
                }
            </style>

            <div class="lh-hero">
                <div class="lh-tile accent-orange">
                    <span class="lh-tile-ico">📦</span>
                    <div class="lh-tile-label">{{ translate("Today's Orders") }}</div>
                    <div class="lh-tile-value">{{ $ordersToday }}</div>
                    @if($ordersPct > 0)
                        <div class="lh-tile-delta up">▲ {{ $ordersPct }}% {{ translate('vs yesterday') }}</div>
                    @elseif($ordersPct < 0)
                        <div class="lh-tile-delta down">▼ {{ abs($ordersPct) }}% {{ translate('vs yesterday') }}</div>
                    @else
                        <div class="lh-tile-delta flat">— {{ translate('same as yesterday') }}</div>
                    @endif
                </div>

                <div class="lh-tile accent-green">
                    <span class="lh-tile-ico">💰</span>
                    <div class="lh-tile-label">{{ translate("Today's Revenue") }}</div>
                    <div class="lh-tile-value">{{ \App\CentralLogics\Helpers::set_symbol($revenueToday) }}</div>
                    @if($revenuePct > 0)
                        <div class="lh-tile-delta up">▲ {{ $revenuePct }}%</div>
                    @elseif($revenuePct < 0)
                        <div class="lh-tile-delta down">▼ {{ abs($revenuePct) }}%</div>
                    @else
                        <div class="lh-tile-delta flat">—</div>
                    @endif
                </div>

                <div class="lh-tile accent-blue">
                    <span class="lh-tile-ico">📊</span>
                    <div class="lh-tile-label">{{ translate('Avg Order Value') }}</div>
                    <div class="lh-tile-value">{{ \App\CentralLogics\Helpers::set_symbol($aovToday) }}</div>
                    <div class="lh-tile-delta flat">{{ translate('today') }}</div>
                </div>

                <div class="lh-tile accent-purple">
                    <span class="lh-tile-ico">🍽</span>
                    <div class="lh-tile-label">{{ translate('Running Tables') }}</div>
                    <div class="lh-tile-value">{{ $runningTables }}</div>
                    @if($pendingKOT > 0)
                        <div class="lh-tile-delta down">{{ $pendingKOT }} {{ translate('need KOT') }}</div>
                    @else
                        <div class="lh-tile-delta up">✓ {{ translate('all sent') }}</div>
                    @endif
                </div>
            </div>

            <div class="lh-quick">
                <a href="{{ route('admin.pos.index') }}">
                    <span class="lh-quick-ico">🛒</span>{{ translate('New Sale') }}
                </a>
                <a href="{{ route('admin.pos.orders') }}">
                    <span class="lh-quick-ico">📦</span>{{ translate('In-Restaurant Orders') }}
                </a>
                <a href="{{ route('admin.table.order.running') }}">
                    <span class="lh-quick-ico">🍽</span>{{ translate('Running Tables') }}
                </a>
                <a href="{{ route('admin.product.add-new') }}">
                    <span class="lh-quick-ico">➕</span>{{ translate('Add Product') }}
                </a>
            </div>

            @if(Helpers::module_permission_check(MANAGEMENT_SECTION['dashboard_management']))

            <div class="card card-body mb-3">
                <div class="row justify-content-between align-items-center g-2 mb-3">
                    <div class="col-auto">
                        <h4 class="d-flex align-items-center gap-10 mb-0">
                            <img width="20" class="avatar-img rounded-0" src="{{asset('public/assets/admin/img/icons/business_analytics.png')}}" alt="Business Analytics">
                            {{translate('Business_Analytics')}}
                        </h4>
                    </div>
                    <div class="col-auto">
                        <select class="custom-select min-w200" name="statistics_type" onchange="order_stats_update(this.value)">
                            <option value="overall" {{session()->has('statistics_type') && session('statistics_type') == 'overall'?'selected':''}}>
                                {{translate('Overall Statistics')}}
                            </option>
                            <option value="today" {{session()->has('statistics_type') && session('statistics_type') == 'today'?'selected':''}}>
                                {{translate("Today")."'s"}} {{translate("Statistics")}}
                            </option>
                            <option value="this_month" {{session()->has('statistics_type') && session('statistics_type') == 'this_month'?'selected':''}}>
                                {{translate("This Month")."'s"}} {{translate("Statistics")}}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="row g-2" id="order_stats">
                    @include('admin-views.partials._dashboard-order-stats',['data'=>$data])
                </div>
            </div>

            <div class="grid-chart mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                            <h4 class="d-flex align-items-center text-capitalize gap-10 mb-0">
                                <img width="20" class="avatar-img rounded-0" src="{{asset('public/assets/admin/img/icons/earning_statistics.png')}}" alt="">
                                {{translate('order_statistics')}}
                            </h4>

                            <ul class="option-select-btn">
                                <li>
                                    <label>
                                        <input type="radio" name="statistics" hidden checked>
                                        <span data-order-type="yearOrder"
                                              onclick="orderStatisticsUpdate(this)">{{translate('This_Year')}}</span>
                                    </label>
                                </li>
                                <li>
                                    <label>
                                        <input type="radio" name="statistics" hidden="">
                                        <span data-order-type="MonthOrder"
                                              onclick="orderStatisticsUpdate(this)">{{translate('This_Month')}}</span>
                                    </label>
                                </li>
                                <li>
                                    <label>
                                        <input type="radio" name="statistics" hidden="">
                                        <span data-order-type="WeekOrder"
                                              onclick="orderStatisticsUpdate(this)">{{translate('This Week')}}</span>
                                    </label>
                                </li>
                            </ul>
                        </div>

                        <div id="updatingOrderData" class="custom-chart mt-2">
                            <div id="order-statistics-line-chart"></div>
                        </div>
                    </div>
                </div>

                <div class="card h-100 order-last order-lg-0">
                    <div class="card-header">
                        <h4 class="d-flex text-capitalize mb-0">
                            {{translate('order_status_statistics')}}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="mt-2">
                            <div>
                                <div class="position-relative pie-chart">
                                    <div id="dognut-pie"></div>
                                    <div class="total--orders">
                                        <h3>{{$donut['pending'] + $donut['ongoing'] + $donut['delivered']+ $donut['canceled']+ $donut['returned']+ $donut['failed']}} </h3>
                                        <span>{{ translate('orders') }}</span>
                                    </div>
                                </div>
                                <div class="apex-legends">
                                    <div class="before-bg-pending">
                                        <span>{{ translate('pending') }} ({{$donut['pending']}})</span>
                                    </div>
                                    <div class="before-bg-ongoing">
                                        <span>{{ translate('ongoing') }} ({{$donut['ongoing']}})</span>
                                    </div>
                                    <div class="before-bg-delivered">
                                        <span>{{ translate('delivered') }} ({{$donut['delivered']}})</span>
                                    </div>
                                    <div class="before-bg-17202A">
                                        <span>{{ translate('canceled') }} ({{$donut['canceled']}})</span>
                                    </div>
                                    <div class="before-bg-21618C">
                                        <span>{{ translate('returned') }} ({{$donut['returned']}})</span>
                                    </div>
                                    <div class="before-bg-27AE60">
                                        <span>{{ translate('failed_to_deliver') }} ({{$donut['failed']}})</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card h100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                            <h4 class="d-flex align-items-center text-capitalize gap-10 mb-0">
                                <img width="20" class="avatar-img rounded-0" src="{{asset('public/assets/admin/img/icons/earning_statistics.png')}}" alt="">
                                {{translate('earning_statistics')}}
                            </h4>
                            <ul class="option-select-btn">
                                <li>
                                    <label>
                                        <input type="radio" name="statistics2" hidden="" checked="">
                                        <span data-earn-type="yearEarn"
                                              onclick="earningStatisticsUpdate(this)">{{translate('This_Year')}}</span>
                                    </label>
                                </li>
                                <li>
                                    <label>
                                        <input type="radio" name="statistics2" hidden="">
                                        <span data-earn-type="MonthEarn"
                                              onclick="earningStatisticsUpdate(this)">{{translate('This_Month')}}</span>
                                    </label>
                                </li>
                                <li>
                                    <label>
                                        <input type="radio" name="statistics2" hidden="">
                                        <span data-earn-type="WeekEarn"
                                              onclick="earningStatisticsUpdate(this)">{{translate('This Week')}}</span>
                                    </label>
                                </li>
                            </ul>
                        </div>

                        <div id="updatingData" class="custom-chart mt-2">
                            <div id="line-adwords"></div>
                        </div>
                    </div>
                </div>

                <div class="card h100 recent-orders">
                    <div class="card-header d-flex justify-content-between gap-10">
                        <h5 class="mb-0">{{translate('recent_Orders')}}</h5>
                        <a href="{{ route('admin.orders.list', ['status' => 'all']) }}" class="btn-link">{{translate('View_All')}}</a>
                    </div>
                    <div class="card-body">
                        <ul class="common-list">
                            @foreach($data['recent_orders'] as $recent)
                                <li class="pt-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                                    <div class="order-info ">
                                        <h5><a href="{{route('admin.orders.details', ['id' => $recent->id])}}" class="text-dark" >{{translate('Order')}}# {{$recent->id}}</a></h5>
                                        <p>{{\Illuminate\Support\Carbon::parse($recent->created_at)->format('d-m-y, h:m A')}}</p>
                                    </div>
                                    @if($recent['order_status'] == 'pending')
                                        <span
                                            class="status text-primary">{{translate($recent['order_status'])}}</span>
                                    @elseif($recent['order_status'] == 'delivered')
                                        <span
                                            class="status text-success">{{translate($recent['order_status'])}}</span>
                                    @elseif($recent['order_status'] == 'confirmed' || $recent['order_status'] == 'processing' || $recent['order_status'] == 'out_for_delivery')
                                        <span
                                            class="status text-warning">{{translate($recent['order_status'])}}</span>
                                    @elseif($recent['order_status'] == 'canceled' || $recent['order_status'] == 'failed')
                                        @if($recent['order_status'] == 'failed')
                                            <span
                                                class="status text-warning">{{translate('failed_to_deliver')}}</span>
                                        @else
                                            <span
                                                class="status text-warning">{{translate($recent['order_status'])}}</span>
                                        @endif

                                    @elseif($recent['order_status'] == 'cooking')
                                        <span
                                            class="status text-info">{{translate($recent['order_status'])}}</span>
                                    @elseif($recent['order_status'] == 'completed')
                                        <span
                                            class="status text-success">{{translate($recent['order_status'])}}</span>
                                    @else
                                        <span
                                            class="status text-primary">{{translate($recent['order_status'])}}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="row g-2">
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        @include('admin-views.partials._top-selling-products',['top_sell'=>$data['top_sell']])
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        @include('admin-views.partials._most-rated-products',['most_rated_products'=>$data['most_rated_products']])
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        @include('admin-views.partials._top-customer',['top_customer'=>$data['top_customer']])
                    </div>
                </div>
            </div>
            @endif
        </div>
{{--    @endif--}}
        @endsection

        @push('script')
            <script src="{{asset('public/assets/admin')}}/vendor/chart.js/dist/Chart.min.js"></script>
            <script src="{{asset('public/assets/admin')}}/vendor/chart.js.extensions/chartjs-extensions.js"></script>
            <script src="{{asset('public/assets/admin')}}/vendor/chartjs-plugin-datalabels/dist/chartjs-plugin-datalabels.min.js"></script>
            <script src="{{asset('public/assets/admin')}}/vendor/apex/apexcharts.min.js"></script>
        @endpush


        @push('script_2')
            <script>
                var OSDCoptions = {
                    chart: {
                        height: 328,
                        type: 'line',
                        zoom: {
                            enabled: false
                        },
                        toolbar: {
                            show: false,
                        },
                    },
                    stroke: {
                        curve: 'smooth',
                        width: 3
                    },
                    colors: ['rgba(255, 111, 112, 0.5)', '#107980'],
                    series: [{
                        name: "Order",
                            data: [
                                {{$order_statistics_chart[1]}}, {{$order_statistics_chart[2]}}, {{$order_statistics_chart[3]}}, {{$order_statistics_chart[4]}},
                                {{$order_statistics_chart[5]}}, {{$order_statistics_chart[6]}}, {{$order_statistics_chart[7]}}, {{$order_statistics_chart[8]}},
                                {{$order_statistics_chart[9]}}, {{$order_statistics_chart[10]}}, {{$order_statistics_chart[11]}}, {{$order_statistics_chart[12]}}
                            ]
                        },
                    ],
                    markers: {
                        size: 2,
                        strokeWidth: 0,
                        hover: {
                            size: 5
                        }
                    },
                    grid: {
                        show: true,
                        padding: {
                            bottom: 0
                        },
                        borderColor: "rgba(180, 208, 224, 0.5)",
                        strokeDashArray: 7,
                        xaxis: {
                            lines: {
                                show: true
                            }
                        }
                    },
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    xaxis: {
                        tooltip: {
                            enabled: false
                        }
                    },
                    legend: {
                        show: false,
                        position: 'top',
                        horizontalAlign: 'right',
                        offsetY: 10
                    }
                }

                var chartLine = new ApexCharts(document.querySelector('#order-statistics-line-chart'), OSDCoptions);
                chartLine.render();
            </script>

            <script>
                var options = {
                    series: [{{$donut['ongoing']}}, {{$donut['delivered']}}, {{$donut['pending']}}, {{$donut['canceled']}}, {{$donut['returned']}}, {{$donut['failed']}}],
                    chart: {
                        width: 256,
                        type: 'donut',
                    },
                    labels: ['{{ translate('ongoing') }}', '{{ translate('delivered') }}', '{{ translate('pending') }}', '{{translate('canceled')}}', '{{translate('returned')}}', '{{translate('failed_to_deliver')}}'],
                    dataLabels: {
                        enabled: false,
                        style: {
                            colors: ['#803838', '#27AE60', '#FF6F70', '#17202A', '#21618C', '#FF0000']
                        }
                    },
                    responsive: [{
                        breakpoint: 1650,
                        options: {
                            chart: {
                                width: 250
                            },
                        }
                    }],
                    colors: ['#803838', '#27AE60', '#FF6F70', '#17202A', '#21618C', '#FF0000'],
                    fill: {
                        colors: ['#803838', '#27AE60', '#FF6F70', '#17202A', '#21618C', '#FF0000']
                    },
                    legend: {
                        show: false
                    },
                };

                var chart = new ApexCharts(document.querySelector("#dognut-pie"), options);
                chart.render();

            </script>

            <script>
                var earningOptions = {
                    chart: {
                        height: 328,
                        type: 'line',
                        zoom: {
                        enabled: false
                        },
                        toolbar: {
                            show: false,
                        },
                    },
                    stroke: {
                        curve: 'straight',
                        width: 3
                    },
                    colors: ['rgba(255, 111, 112, 0.5)', '#107980'],
                    series: [{
                        name: "Earning",
                        data: [{{$earning[1]}}, {{$earning[2]}}, {{$earning[3]}}, {{$earning[4]}}, {{$earning[5]}}, {{$earning[6]}},
                            {{$earning[7]}}, {{$earning[8]}}, {{$earning[9]}}, {{$earning[10]}}, {{$earning[11]}}, {{$earning[12]}}],
                        },
                    ],
                    markers: {
                        size: 2,
                        strokeWidth: 0,
                        hover: {
                            size: 5
                        }
                    },
                    grid: {
                        show: true,
                        padding: {
                            bottom: 0
                        },
                        borderColor: "rgba(180, 208, 224, 0.5)",
                        strokeDashArray: 7,
                        xaxis: {
                            lines: {
                                show: true
                            }
                        }
                    },
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    xaxis: {
                        tooltip: {
                            enabled: false
                        }
                    },
                    legend: {
                        show: false,
                        position: 'top',
                        horizontalAlign: 'right',
                        offsetY: 10
                    }
                }

                var chartLine = new ApexCharts(document.querySelector('#line-adwords'), earningOptions);
                chartLine.render();
            </script>

            <script>
                function order_stats_update(type) {
                    console.log(type)
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });

                    $.ajax({
                        url: "{{route('admin.order-stats')}}",
                        type: "post",
                        data: {
                            statistics_type: type,
                        },
                        beforeSend: function () {
                            $('#loading').show()
                        },
                        success: function (data) {
                            $('#order_stats').html(data.view)
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.log(textStatus, errorThrown);
                        },
                        complete: function () {
                            $('#loading').hide()
                        }
                    });
                }
            </script>

            <script>
                Chart.plugins.unregister(ChartDataLabels);

                $('.js-chart').each(function () {
                    $.HSCore.components.HSChartJS.init($(this));
                });

                var updatingChart = $.HSCore.components.HSChartJS.init($('#updatingData'));

            </script>
            <script>
                function orderStatisticsUpdate(t) {
                    let value = $(t).attr('data-order-type');
                    console.log(value);

                    $.ajax({
                        url: '{{route('admin.order-statistics')}}',
                        type: 'GET',
                        data: {
                            type: value
                        },
                        beforeSend: function () {
                            $('#loading').show()
                        },
                        success: function (response_data) {
                            document.getElementById("order-statistics-line-chart").remove();
                            let graph = document.createElement('div');
                            graph.setAttribute("id", "order-statistics-line-chart");
                            document.getElementById("updatingOrderData").appendChild(graph);

                            var options = {
                                series: [{
                                    name: "Orders",
                                    data: response_data.orders,
                                }],
                                chart: {
                                    height: 316,
                                    type: 'line',
                                    zoom: {
                                        enabled: false
                                    },
                                    toolbar: {
                                        show: false,
                                    },
                                    markers: {
                                        size: 5,
                                    }
                                },
                                dataLabels: {
                                    enabled: false,
                                },
                                colors: ['rgba(255, 111, 112, 0.5)', '#107980'],
                                stroke: {
                                    curve: 'smooth',
                                    width: 3,
                                },
                                xaxis: {
                                    categories: response_data.orders_label,
                                },
                                grid: {
                                    show: true,
                                    padding: {
                                        bottom: 0
                                    },
                                    borderColor: "rgba(180, 208, 224, 0.5)",
                                    strokeDashArray: 7,
                                    xaxis: {
                                        lines: {
                                            show: true
                                        }
                                    }
                                },
                                yaxis: {
                                    tickAmount: 4,
                                }
                            };

                            var chart = new ApexCharts(document.querySelector("#order-statistics-line-chart"), options);
                            chart.render();
                        },
                        complete: function () {
                            $('#loading').hide()
                        }
                    });
                }

                function earningStatisticsUpdate(t) {
                    let value = $(t).attr('data-earn-type');
                    $.ajax({
                        url: '{{route('admin.earning-statistics')}}',
                        type: 'GET',
                        data: {
                            type: value
                        },
                        beforeSend: function () {
                            $('#loading').show()
                        },
                        success: function (response_data) {
                            console.log(response_data)
                            document.getElementById("line-adwords").remove();
                            let graph = document.createElement('div');
                            graph.setAttribute("id", "line-adwords");
                            document.getElementById("updatingData").appendChild(graph);

                            var optionsLine = {
                                chart: {
                                    height: 328,
                                    type: 'line',
                                    zoom: {
                                        enabled: false
                                    },
                                    toolbar: {
                                        show: false,
                                    },
                                },
                                stroke: {
                                    curve: 'straight',
                                    width: 2
                                },
                                colors: ['rgba(255, 111, 112, 0.5)', '#107980'],
                                series: [{
                                    name: "Earning",
                                    data: response_data.earning,
                                }],
                                markers: {
                                    size: 6,
                                    strokeWidth: 0,
                                    hover: {
                                        size: 9
                                    }
                                },
                                grid: {
                                    show: true,
                                    padding: {
                                        bottom: 0
                                    },
                                    borderColor: "rgba(180, 208, 224, 0.5)",
                                    strokeDashArray: 7,
                                    xaxis: {
                                        lines: {
                                            show: true
                                        }
                                    }
                                },
                                labels: response_data.earning_label,
                                xaxis: {
                                    tooltip: {
                                        enabled: false
                                    }
                                },
                                legend: {
                                    position: 'top',
                                    horizontalAlign: 'right',
                                    offsetY: -20
                                }
                            }
                            var chartLine = new ApexCharts(document.querySelector('#line-adwords'), optionsLine);
                            chartLine.render();
                        },
                        complete: function () {
                            $('#loading').hide()
                        }
                    });
                }
            </script>

        @endpush
