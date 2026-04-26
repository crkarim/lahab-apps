@extends('layouts.admin.app')

@section('title', translate('Active Orders'))

@php
    // Helper: build the type-filter URL while preserving the branch + table
    // filter so switching tabs doesn't reset the operator's context.
    $tabUrl = function ($tabType) use ($branchId, $table_id) {
        $params = ['type' => $tabType, 'branch' => $branchId];
        if ($tabType === 'dine_in' && !is_null($table_id)) {
            $params['table_id'] = $table_id;
        }
        return route('admin.table.order.running', $params);
    };
    // Active class for the currently-selected tab.
    $activeTab = fn($t) => $t === ($type ?? 'all') ? 'active' : '';
@endphp

@section('content')
    <div class="container-fluid py-5">
        <div>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
                <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                    <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/all_orders.png')}}" alt="">
                    <span class="page-header-title">
                    {{translate('Active Orders')}}
                </span>
                </h2>
                <span class="badge badge-soft-dark rounded-50 fz-14">{{$orders->total()}}</span>
            </div>

            {{-- Tab strip: lets the operator pivot between dine-in, delivery,
                 and take-away in one place. Counts come from the controller
                 so each badge reflects the live in-progress queue. --}}
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab('all') }}" href="{{ $tabUrl('all') }}">
                        {{ translate('All') }}
                        <span class="badge badge-soft-dark rounded-pill ml-1">{{ $counts['all'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab('dine_in') }}" href="{{ $tabUrl('dine_in') }}">
                        {{ translate('Dine-in') }}
                        <span class="badge badge-soft-info rounded-pill ml-1">{{ $counts['dine_in'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab('delivery') }}" href="{{ $tabUrl('delivery') }}">
                        {{ translate('Delivery') }}
                        <span class="badge badge-soft-warning rounded-pill ml-1">{{ $counts['delivery'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab('take_away') }}" href="{{ $tabUrl('take_away') }}">
                        {{ translate('Take-away') }}
                        <span class="badge badge-soft-success rounded-pill ml-1">{{ $counts['take_away'] }}</span>
                    </a>
                </li>
            </ul>
        </div>
        <div id="all_running_order">
            <div class="card">
                <div class="card-top px-card pt-4">
                    <div class="row justify-content-between align-items-center gy-2">
                        <div class="col-sm-4 col-md-5 col-lg-4">
                            <div>
                                <form action="" method="GET">
                                    <div class="input-group">
                                        <input id="datatableSearch_" type="search" name="search" class="form-control" placeholder="Search by ID  customer or payment status" aria-label="Search" value="{{ request()->search }}" required="" autocomplete="off">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">{{ translate('Search') }}</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="col-sm-6 col-md-6 col-lg-7">
                            <div>
                                 <form  class="row" method="get" action="">
                                     {{-- Preserve the active tab when the operator
                                          changes branch or table filters. --}}
                                     <input type="hidden" name="type" value="{{ $type ?? 'all' }}">
                                     @if(($type ?? 'all') === 'dine_in')
                                         <div class="col-md-3">
                                             <div id="invoice_btn" class="{{ is_null($table_id) ? 'd-none' : '' }}">
                                                 <a class="form-control btn btn-sm btn-white float-right" href="{{ route('admin.table.order.running.invoice', ['table_id' => $table_id]) }}"><i class="tio-print"></i> {{translate('invoice')}}</a>
                                             </div>
                                         </div>
                                     @endif
                                     <div class="col-md-{{ ($type ?? 'all') === 'dine_in' ? '3' : '4' }}">
                                         <select class="form-control text-capitalize filter-branch-orders" name="branch">
                                             <option disabled>--- {{translate('select')}} {{translate('branch')}} ---</option>
                                             @foreach(\App\Model\Branch::all() as $branch)
                                                 <option value="{{$branch['id']}}" {{$branchId==$branch['id']?'selected':''}}>{{$branch['name']}}</option>
                                             @endforeach
                                         </select>
                                     </div>
                                     @if(($type ?? 'all') === 'dine_in')
                                         {{-- Table-specific filter only makes sense
                                              for dine-in; delivery and take-away
                                              orders aren't bound to a table. --}}
                                         <div class="col-md-3">
                                             <select class="form-control text-capitalize" name="table" id="select_table">
                                                 @foreach($tables as $table)
                                                     <option value="{{ $table['id'] }}" {{ $table_id == $table['id'] ? 'selected' : '' }}>{{ translate('Table') }} - {{ $table['number'] }}{{ $table['zone'] ? ' · ' . $table['zone'] : '' }}</option>
                                                 @endforeach
                                             </select>
                                         </div>
                                     @endif
                                     <div class="col-md-{{ ($type ?? 'all') === 'dine_in' ? '3' : '4' }}">
                                         <button type="submit" class="btn btn-primary w-100">filter</button>
                                     </div>
                                 </form>


                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body mt-4 px-3">
                    <div class="table-responsive datatable-custom">
                        <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table width-100-percent">
                            <thead class="thead-light">
                            <tr>
                                <th>{{translate('SL')}}</th>
                                <th class="table-column-pl-0">{{translate('order')}}</th>
                                <th>{{translate('type')}}</th>
                                <th>{{translate('time')}}</th>
                                <th>{{translate('branch')}}</th>
                                <th>{{translate('table')}}</th>
                                <th>{{translate('placed by')}}</th>
                                <th>{{translate('payment')}}</th>
                                <th>{{translate('total')}}</th>
                                <th>{{translate('order')}} {{translate('status')}}</th>
                                <th class="text-center">{{translate('actions')}}</th>
                            </tr>
                            </thead>

                            <tbody id="set-rows">
                            @foreach($orders as $key=>$order)
                                @php
                                    // Per-row context flags so each action button
                                    // is a one-liner instead of nested ternaries.
                                    $isInRestaurant = in_array($order->order_type, ['pos', 'take_away', 'dine_in'], true);
                                    $isTakeAwayRow  = in_array($order->order_type, ['pos', 'take_away'], true);
                                    $isDineInRow    = $order->order_type === 'dine_in';
                                    $isDeliveryRow  = $order->order_type === 'delivery';
                                    // "Add Items" appendable when the kitchen is
                                    // still working on a dine-in/POS order. Mirrors
                                    // the rule on the order details page.
                                    $canAppend      = $isInRestaurant
                                        && $order->payment_status !== 'paid'
                                        && in_array($order->order_status, ['pending', 'confirmed', 'processing', 'cooking', 'done'], true);
                                    // KOT can be (re)printed anytime kitchen is
                                    // still relevant — same gate as the details page.
                                    $canPrintKot    = in_array($order->order_status, ['pending', 'confirmed', 'cooking', 'processing'], true);
                                @endphp
                                <tr class="status-{{$order['order_status']}} class-all lh-row-clickable"
                                    data-href="{{ route('admin.orders.details', ['id' => $order['id']]) }}"
                                    title="{{ translate('Click to view order details') }}">
                                    <td>{{$orders->firstitem()+$key}}</td>
                                    <td class="table-column-pl-0">
                                        <strong>#{{$order['id']}}</strong>
                                    </td>
                                    <td>
                                        {{-- Type badge — keeps operators oriented when the All tab mixes
                                             order kinds. Helpers::order_type_label maps the legacy
                                             'pos' value to "Take Away" so the UI is honest. --}}
                                        @php
                                            $typeLabel = \App\CentralLogics\Helpers::order_type_label($order->order_type);
                                            $typeClass = match ($order->order_type) {
                                                'dine_in'             => 'badge-soft-info',
                                                'delivery'            => 'badge-soft-warning',
                                                'pos', 'take_away'    => 'badge-soft-success',
                                                default               => 'badge-soft-secondary',
                                            };
                                        @endphp
                                        <label class="badge {{ $typeClass }}">{{ $typeLabel }}</label>
                                    </td>
                                    <td title="{{ \Carbon\Carbon::parse($order['created_at'])->format('d M Y, H:i') }}">
                                        {{-- Compact time + relative on hover so a busy
                                             operator can scan the queue at a glance. --}}
                                        <span class="d-block">{{ \Carbon\Carbon::parse($order['created_at'])->format('H:i') }}</span>
                                        <small class="text-muted">{{ \Carbon\Carbon::parse($order['created_at'])->diffForHumans(null, true) }}</small>
                                    </td>
                                    <td>
                                        <label class="badge badge-soft-primary">{{$order->branch?$order->branch->name:'Branch deleted!'}}</label>
                                    </td>
                                    <td>
                                        @if($order->table)
                                            <label class="badge badge-soft-info">{{translate('table')}} - {{$order->table->number}}{{ $order->table->zone ? ' · ' . $order->table->zone : '' }}</label>
                                        @elseif(in_array($order->order_type, ['delivery', 'pos', 'take_away']))
                                            <span class="text-muted">—</span>
                                        @else
                                            <label class="badge badge-soft-info">{{translate('table deleted')}}</label>
                                        @endif
                                    </td>
                                    <td>
                                        {{-- Placed By — admin/cashier/waiter who created
                                             the order at the POS. Null for self-service
                                             web/app orders where the customer placed it. --}}
                                        @if($order->placedBy)
                                            <span>{{ $order->placedBy->name }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($order->payment_status=='paid')
                                            <span class="badge badge-soft-success"><span class="legend-indicator bg-success"></span>{{translate('paid')}}</span>
                                        @else
                                            <span class="badge badge-soft-danger"><span class="legend-indicator bg-danger"></span>{{translate('unpaid')}}</span>
                                        @endif
                                    </td>
                                    <td>{{ \App\CentralLogics\Helpers::set_symbol($order['order_amount']) }}</td>
                                    <td class="text-capitalize">
                                        @php
                                            // Status badge colour per state. Type-aware label
                                            // so take-away "done"/"completed" reads as
                                            // "Ready for Handover"/"Handed Over".
                                            $statusBadgeClass = match ($order->order_status) {
                                                'pending', 'confirmed', 'cooking', 'done' => 'badge-soft-info',
                                                'processing', 'out_for_delivery'          => 'badge-soft-warning',
                                                'completed', 'delivered'                  => 'badge-soft-success',
                                                default                                   => 'badge-soft-danger',
                                            };
                                            $indicatorClass = match ($order->order_status) {
                                                'pending', 'confirmed', 'cooking', 'done' => 'bg-info',
                                                'processing', 'out_for_delivery'          => 'bg-warning',
                                                'completed', 'delivered'                  => 'bg-success',
                                                default                                   => 'bg-danger',
                                            };
                                            $statusLabel = \App\CentralLogics\Helpers::order_status_label($order->order_type, $order->order_status);
                                        @endphp
                                        <span class="badge {{ $statusBadgeClass }}"><span class="legend-indicator {{ $indicatorClass }}"></span>{{ $statusLabel }}</span>
                                    </td>
                                    <td>
                                        {{-- Inline action toolbar. Buttons are gated by status
                                             and order type so the operator only sees what's
                                             actually applicable for the row. The .lh-row-action
                                             class on each control prevents the row-click
                                             handler from also navigating to details. --}}
                                        <div class="d-flex justify-content-center align-items-center gap-1 flex-wrap">
                                            @switch($order->order_status)
                                                @case('pending')
                                                @case('confirmed')
                                                    <a class="btn btn-sm btn-outline-warning lh-row-action route-alert"
                                                       href="javascript:"
                                                       data-route="{{ route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'cooking']) }}"
                                                       data-message="{{ translate('Start cooking this order?') }}"
                                                       title="{{ translate('Start Cooking') }}">
                                                        <i class="tio-fire"></i>
                                                    </a>
                                                    @break
                                                @case('cooking')
                                                    <a class="btn btn-sm btn-outline-success lh-row-action route-alert"
                                                       href="javascript:"
                                                       data-route="{{ route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'done']) }}"
                                                       data-message="{{ $isTakeAwayRow ? translate('Mark food as ready for handover?') : translate('Mark food as ready to serve?') }}"
                                                       title="{{ $isTakeAwayRow ? translate('Mark Ready for Handover') : translate('Mark Ready') }}">
                                                        <i class="tio-done"></i>
                                                    </a>
                                                    @break
                                                @case('done')
                                                    @if($isTakeAwayRow)
                                                        <a class="btn btn-sm btn-success lh-row-action route-alert"
                                                           href="javascript:"
                                                           data-route="{{ route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'completed']) }}"
                                                           data-message="{{ translate('Mark this order as handed over to the customer?') }}"
                                                           title="{{ translate('Mark Handed Over') }}">
                                                            <i class="tio-checkmark-circle"></i>
                                                        </a>
                                                    @elseif($isDineInRow)
                                                        {{-- Dine-in checkout needs the modal on
                                                             the details page (split payments etc.). --}}
                                                        <a class="btn btn-sm btn-success lh-row-action"
                                                           href="{{ route('admin.orders.details', ['id' => $order['id']]) }}"
                                                           title="{{ translate('Checkout') }}">
                                                            <i class="tio-checkmark-circle"></i>
                                                        </a>
                                                    @endif
                                                    @break
                                                @case('processing')
                                                    @if($isDeliveryRow)
                                                        <a class="btn btn-sm btn-outline-warning lh-row-action route-alert"
                                                           href="javascript:"
                                                           data-route="{{ route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'out_for_delivery']) }}"
                                                           data-message="{{ translate('Mark order as out for delivery?') }}"
                                                           title="{{ translate('Out For Delivery') }}">
                                                            <i class="tio-bike"></i>
                                                        </a>
                                                    @endif
                                                    @break
                                                @case('out_for_delivery')
                                                    <a class="btn btn-sm btn-success lh-row-action route-alert"
                                                       href="javascript:"
                                                       data-route="{{ route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'delivered']) }}"
                                                       data-message="{{ translate('Confirm order delivered?') }}"
                                                       title="{{ translate('Delivered') }}">
                                                        <i class="tio-checkmark-circle"></i>
                                                    </a>
                                                    @break
                                            @endswitch

                                            @if($canPrintKot)
                                                <a class="btn btn-sm btn-outline-secondary lh-row-action"
                                                   href="{{ route('admin.orders.kitchen-ticket', $order['id']) }}"
                                                   target="_blank"
                                                   title="{{ $order->kot_number ? translate('Reprint KOT') : translate('Print KOT') }}">
                                                    <i class="tio-print"></i>
                                                </a>
                                            @endif

                                            @if($canAppend)
                                                {{-- Add Items modal lives on the details page, so
                                                     we deep-link there — opens with the modal hash
                                                     that JS on the details page picks up. --}}
                                                <a class="btn btn-sm btn-outline-info lh-row-action"
                                                   href="{{ route('admin.orders.details', ['id' => $order['id']]) }}#add-items"
                                                   title="{{ translate('Add Items') }}">
                                                    <i class="tio-add-circle-outlined"></i>
                                                </a>
                                            @endif

                                            <a class="btn btn-sm btn-outline-primary lh-row-action"
                                               href="{{ route('admin.orders.details', ['id' => $order['id']]) }}"
                                               title="{{ translate('View Details') }}">
                                                <i class="tio-invisible"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row justify-content-center justify-content-sm-between align-items-sm-center">
                        <div class="col-sm-auto">
                            <div class="d-flex justify-content-center justify-content-sm-end">
                                {!! $orders->links() !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

@endsection

@push('script_2')
    <script>
        $(document).ready(function () {
            function getParameterByName(name, url = window.location.href) {
                name = name.replace(/[\[\]]/g, '\\$&');
                let regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)');
                let results = regex.exec(url);
                if (!results) return null;
                if (!results[2]) return '';
                return decodeURIComponent(results[2].replace(/\+/g, ' '));
            }

            // Populate tables on branch change
            $('.filter-branch-orders').change(function () {
                let branchId = this.value;
                $.ajax({
                    url: '{{ url('/admin/table/order/tables-by-branch') }}/' + branchId,
                    type: 'GET',
                    success: function (data) {
                        let tableSelect = $('#select_table');
                        tableSelect.empty();
                        tableSelect.append('<option disabled selected>--- {{translate('select')}} {{translate('table')}} ---</option>');
                        data.forEach(function (table) {
                            tableSelect.append('<option value="' + table.id + '">' + '{{translate('Table')}} - ' + table.number + '</option>');
                        });

                        // Set table from URL if available
                        let tableIdFromUrl = getParameterByName('table');
                        if (tableIdFromUrl) {
                            tableSelect.val(tableIdFromUrl);
                        }
                    }
                });
            });

            // Show table name after selection
            $('#select_table').change(function () {
                let selectedTableText = $("#select_table option:selected").text();
                // Optionally handle the selected table text
            });

            // Initially populate tables for the selected or default branch
            let initialBranchId = $('.filter-branch-orders').val();
            if (initialBranchId) {
                $.ajax({
                    url: '{{ url('/admin/table/order/tables-by-branch') }}/' + initialBranchId,
                    type: 'GET',
                    success: function (data) {
                        let tableSelect = $('#select_table');
                        tableSelect.empty();
                        tableSelect.append('<option disabled selected>--- {{translate('select')}} {{translate('table')}} ---</option>');
                        data.forEach(function (table) {
                            tableSelect.append('<option value="' + table.id + '">' + '{{translate('Table')}} - ' + table.number + '</option>');
                        });

                        // Set table from URL if available
                        let tableIdFromUrl = getParameterByName('table');
                        if (tableIdFromUrl) {
                            tableSelect.val(tableIdFromUrl);
                        }
                    }
                });
            }

            // Click anywhere on a row → open order details, EXCEPT on action
            // controls. .lh-row-action is the opt-out class on every button/link
            // in the actions column. Cmd/Ctrl-click and middle-click open in
            // a new tab so the operator can keep multiple orders side-by-side.
            $(document).on('click', 'tr.lh-row-clickable', function (e) {
                if ($(e.target).closest('.lh-row-action, a, button, input, select, .dropdown, .modal').length) {
                    return;
                }
                let href = $(this).data('href');
                if (!href) return;
                if (e.metaKey || e.ctrlKey || e.which === 2) {
                    window.open(href, '_blank');
                } else {
                    window.location = href;
                }
            });

            // Auto-refresh every 30 seconds so the queue reflects new orders +
            // status changes without the operator hammering F5. Skipped if any
            // modal is open (e.g. branch picker) so we don't yank the UI.
            setInterval(function () {
                if ($('.modal.show').length === 0) {
                    window.location.reload();
                }
            }, 30000);
        });
    </script>
@endpush

@push('css_or_js')
    <style>
        /* Clickable-row UX: hover tint + cursor so operators know rows are
           live targets. Action buttons inside the row keep default cursor. */
        tr.lh-row-clickable          { cursor: pointer; }
        tr.lh-row-clickable:hover    { background-color: rgba(230, 126, 34, 0.05); }
        tr.lh-row-clickable .lh-row-action { cursor: pointer; }
    </style>
@endpush
