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
                                        {{-- Fixed 4-slot action toolbar. Same positions on
                                             every row so the operator's muscle memory works
                                             — only the primary slot's label/colour/route
                                             swap with status. Disabled buttons show as
                                             greyed but stay in place. .lh-row-action
                                             prevents the row-click handler from also
                                             navigating to details. --}}
                                        @php
                                            // Slot 1 — primary next-action. Resolved per
                                            // (status × type × delivery_man_id) in one place
                                            // so the markup below stays a single button slot.
                                            $primary = ['enabled' => false, 'class' => 'btn-secondary', 'icon' => 'tio-blocked', 'title' => '—', 'route' => null, 'message' => null];
                                            $status  = $order->order_status;
                                            $hasRider = !empty($order->delivery_man_id);
                                            if ($status === 'cooking') {
                                                if ($isDeliveryRow) {
                                                    $primary = [
                                                        'enabled' => true, 'class' => 'btn-success', 'icon' => 'tio-done',
                                                        'title'   => translate('Mark Ready for Rider'),
                                                        'route'   => route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'done']),
                                                        'message' => translate('Mark food as ready for the rider to pick up?'),
                                                    ];
                                                } elseif ($isTakeAwayRow) {
                                                    $primary = [
                                                        'enabled' => true, 'class' => 'btn-success', 'icon' => 'tio-done',
                                                        'title'   => translate('Mark Ready for Handover'),
                                                        'route'   => route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'done']),
                                                        'message' => translate('Mark food as ready for handover?'),
                                                    ];
                                                } elseif ($isDineInRow) {
                                                    $primary = [
                                                        'enabled' => true, 'class' => 'btn-success', 'icon' => 'tio-done',
                                                        'title'   => translate('Mark Ready'),
                                                        'route'   => route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'done']),
                                                        'message' => translate('Mark food as ready to serve?'),
                                                    ];
                                                }
                                            } elseif ($status === 'done') {
                                                if ($isTakeAwayRow) {
                                                    $primary = [
                                                        'enabled' => true, 'class' => 'btn-success', 'icon' => 'tio-checkmark-circle',
                                                        'title'   => translate('Mark Handed Over'),
                                                        'route'   => route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'completed']),
                                                        'message' => translate('Mark this order as handed over to the customer?'),
                                                    ];
                                                } elseif ($isDineInRow) {
                                                    // Checkout needs the modal on details page.
                                                    $primary = [
                                                        'enabled'   => true, 'class' => 'btn-success', 'icon' => 'tio-checkmark-circle',
                                                        'title'     => translate('Checkout'),
                                                        'href'      => route('admin.orders.details', ['id' => $order['id']]),
                                                    ];
                                                } elseif ($isDeliveryRow && !$hasRider) {
                                                    // No rider yet — send the operator to the
                                                    // existing assign-rider modal on details.
                                                    $primary = [
                                                        'enabled' => true, 'class' => 'btn-info', 'icon' => 'tio-user-add',
                                                        'title'   => translate('Assign Rider'),
                                                        'href'    => route('admin.orders.details', ['id' => $order['id']]) . '#assignDeliveryMan',
                                                    ];
                                                } elseif ($isDeliveryRow && $hasRider) {
                                                    $primary = [
                                                        'enabled' => true, 'class' => 'btn-success', 'icon' => 'tio-bike',
                                                        'title'   => translate('Handed to Rider'),
                                                        'route'   => route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'out_for_delivery']),
                                                        'message' => translate('Handed the food to the rider for delivery?'),
                                                    ];
                                                }
                                            } elseif ($status === 'out_for_delivery') {
                                                $primary = [
                                                    'enabled' => true, 'class' => 'btn-success', 'icon' => 'tio-checkmark-circle',
                                                    'title'   => translate('Mark Delivered'),
                                                    'route'   => route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'delivered']),
                                                    'message' => translate('Confirm order delivered?'),
                                                ];
                                            }
                                            // Slot 2 — Kitchen. Send to Kitchen does both:
                                            // print KOT + flip status to cooking. Past
                                            // confirmed it degrades to "Reprint KOT" with no
                                            // status side-effect.
                                            $kitchenSendable = in_array($status, ['pending', 'confirmed'], true);
                                            $kitchenReprintable = in_array($status, ['cooking', 'processing', 'done'], true);
                                        @endphp

                                        <div class="d-flex justify-content-center align-items-center gap-1 flex-nowrap">
                                            {{-- SLOT 1 — Primary action --}}
                                            @if($primary['enabled'] && isset($primary['route']))
                                                <a class="btn btn-sm {{ $primary['class'] }} lh-row-action lh-action-fixed route-alert"
                                                   href="javascript:"
                                                   data-route="{{ $primary['route'] }}"
                                                   data-message="{{ $primary['message'] }}"
                                                   title="{{ $primary['title'] }}">
                                                    <i class="{{ $primary['icon'] }}"></i>
                                                </a>
                                            @elseif($primary['enabled'] && isset($primary['href']))
                                                <a class="btn btn-sm {{ $primary['class'] }} lh-row-action lh-action-fixed"
                                                   href="{{ $primary['href'] }}"
                                                   title="{{ $primary['title'] }}">
                                                    <i class="{{ $primary['icon'] }}"></i>
                                                </a>
                                            @else
                                                <span class="btn btn-sm btn-light lh-row-action lh-action-fixed disabled"
                                                      title="{{ translate('Waiting for next step') }}">
                                                    <i class="tio-time"></i>
                                                </span>
                                            @endif

                                            {{-- SLOT 2 — Send to Kitchen / Reprint KOT.
                                                 Pending / confirmed orders MUST be sent to
                                                 the kitchen before anything else can happen,
                                                 so this slot uses btn-danger (red) + a pulse
                                                 to draw the operator's eye. --}}
                                            @if($kitchenSendable)
                                                <a class="btn btn-sm btn-danger lh-row-action lh-action-fixed lh-send-to-kitchen lh-action-urgent"
                                                   href="javascript:"
                                                   data-kot="{{ route('admin.orders.kitchen-ticket', $order['id']) }}"
                                                   data-cook-status="{{ route('admin.orders.status', ['id' => $order['id'], 'order_status' => 'cooking']) }}"
                                                   title="{{ translate('Send to Kitchen — required next step') }}">
                                                    <i class="tio-send"></i>
                                                </a>
                                            @elseif($kitchenReprintable)
                                                <a class="btn btn-sm btn-outline-secondary lh-row-action lh-action-fixed"
                                                   href="{{ route('admin.orders.kitchen-ticket', $order['id']) }}"
                                                   target="_blank"
                                                   title="{{ translate('Reprint KOT') }}">
                                                    <i class="tio-print"></i>
                                                </a>
                                            @else
                                                <span class="btn btn-sm btn-light lh-row-action lh-action-fixed disabled"
                                                      title="{{ translate('Kitchen — not applicable') }}">
                                                    <i class="tio-print"></i>
                                                </span>
                                            @endif

                                            {{-- SLOT 3 — Add Items (deep-link to modal) --}}
                                            @if($canAppend)
                                                <a class="btn btn-sm btn-info lh-row-action lh-action-fixed"
                                                   href="{{ route('admin.orders.details', ['id' => $order['id']]) }}#add-items"
                                                   title="{{ translate('Add Items') }}">
                                                    <i class="tio-add-circle-outlined"></i>
                                                </a>
                                            @else
                                                <span class="btn btn-sm btn-light lh-row-action lh-action-fixed disabled"
                                                      title="{{ translate('Cannot add items at this stage') }}">
                                                    <i class="tio-add-circle-outlined"></i>
                                                </span>
                                            @endif

                                            {{-- SLOT 4 — View Details (always enabled) --}}
                                            <a class="btn btn-sm btn-outline-primary lh-row-action lh-action-fixed"
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

            // Send to Kitchen — chain two existing endpoints in one click:
            // (1) open the KOT printable in a new tab so the kitchen has the
            // ticket; (2) navigate the current tab to the status update so
            // the order moves to `cooking`. Confirmation modal first so a
            // mis-click doesn't fire the print job. No backend changes.
            $(document).on('click', '.lh-send-to-kitchen', function (e) {
                e.preventDefault();
                const kotUrl    = $(this).data('kot');
                const cookUrl   = $(this).data('cook-status');
                Swal.fire({
                    title: '{{ translate("Send to Kitchen?") }}',
                    text:  '{{ translate("This will print the kitchen ticket and start cooking.") }}',
                    type: 'warning',
                    showCancelButton: true,
                    cancelButtonColor: 'default',
                    confirmButtonColor: '#E67E22',
                    cancelButtonText:  '{{ translate("Cancel") }}',
                    confirmButtonText: '{{ translate("Yes, Send") }}',
                    reverseButtons: true
                }).then((result) => {
                    if (!result.value) return;
                    // Open KOT first so the new-tab pop happens inside the
                    // user-gesture window — popup blockers won't fire.
                    if (kotUrl) {
                        window.open(kotUrl, '_blank');
                    }
                    // Navigate THIS tab to the status update; the controller
                    // redirects back to the listing on success.
                    if (cookUrl) {
                        window.location = cookUrl;
                    }
                });
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

        /* Fixed-width slots in the actions column so a disabled button in
           slot 2 doesn't shove slot 3+4 to a new horizontal position. The
           operator's eye stays trained on the same x-coordinates regardless
           of order status. */
        .lh-action-fixed {
            width: 36px; height: 36px;
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0; flex: 0 0 36px;
        }
        .lh-action-fixed.disabled {
            background-color: #f5f5f5; color: #c8c8c8; border-color: #e9e9e9;
            cursor: not-allowed; opacity: 0.55;
        }
        .lh-action-fixed i { font-size: 1.05rem; }

        /* Urgent next-step (Send to Kitchen) — soft pulsing red shadow so
           the operator's eye locks onto pending orders even in a long
           queue. Subtle enough to not feel like a strobe light. */
        .lh-action-urgent {
            animation: lh-urgent-pulse 1.6s ease-in-out infinite;
        }
        @keyframes lh-urgent-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.55); }
            50%      { box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .lh-action-urgent { animation: none; }
        }
    </style>
@endpush
