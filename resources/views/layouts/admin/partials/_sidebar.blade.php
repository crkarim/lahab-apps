<div id="sidebarMain" class="d-none">
    <aside class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered">
        <div class="navbar-vertical-container text-capitalize">
            <div class="navbar-vertical-footer-offset">

                {{-- Logo + collapse toggle --}}
                <div class="navbar-brand-wrapper justify-content-between">
                    <?php $restaurant_logo = \App\Model\BusinessSetting::where(['key'=>'logo'])->first()->value ?? null; ?>
                    <a class="navbar-brand" href="{{ route('admin.dashboard') }}" aria-label="Home">
                        <img class="navbar-brand-logo" style="object-fit: contain;"
                             onerror="this.src='{{ asset('public/assets/admin/img/160x160/img2.jpg') }}'"
                             src="{{ asset('storage/app/public/restaurant/'.$restaurant_logo) }}" alt="Logo">
                        <img class="navbar-brand-logo-mini" style="object-fit: contain;"
                             onerror="this.src='{{ asset('public/assets/admin/img/160x160/img2.jpg') }}'"
                             src="{{ asset('storage/app/public/restaurant/'.$restaurant_logo) }}" alt="Logo">
                    </a>

                    <button type="button" class="js-navbar-vertical-aside-toggle-invoker navbar-vertical-aside-toggle btn btn-icon btn-xs btn-ghost-dark">
                        <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip" data-placement="right" title="Collapse"></i>
                        <i class="tio-last-page navbar-vertical-aside-toggle-full-align" data-toggle="tooltip" data-placement="right" title="Expand"></i>
                    </button>

                    <div class="navbar-nav-wrap-content-left d-none d-xl-block">
                        <button type="button" class="js-navbar-vertical-aside-toggle-invoker close">
                            <i class="tio-first-page navbar-vertical-aside-toggle-short-align"></i>
                            <i class="tio-last-page navbar-vertical-aside-toggle-full-align"></i>
                        </button>
                    </div>
                </div>

                <div class="navbar-vertical-content">

                    <ul class="navbar-nav navbar-nav-lg nav-tabs">

                        {{-- 1. Dashboard --}}
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin') ? 'show' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.dashboard') }}" title="{{ translate('Dashboard') }}">
                                <i class="tio-home-vs-1-outlined nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Dashboard') }}</span>
                            </a>
                        </li>

                        {{-- 2. POS — direct link, most-used action --}}
                        @if(Helpers::module_permission_check(MANAGEMENT_SECTION['pos_management']))
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/pos') ? 'show' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.pos.index') }}" title="{{ translate('POS') }}">
                                    <i class="tio-shopping nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('POS · New Sale') }}</span>
                                </a>
                            </li>
                        @endif

                        {{-- 3. Orders --}}
                        @if(Helpers::module_permission_check(MANAGEMENT_SECTION['order_management']))
                            <li class="navbar-vertical-aside-has-menu
                                {{ Request::is('admin/pos/orders*') || Request::is('admin/orders*') || Request::is('admin/table/order*') || Request::is('admin/verify-offline-payment*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Orders') }}">
                                    <i class="tio-shopping-cart nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Orders') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                    style="display: {{ Request::is('admin/pos/orders*') || Request::is('admin/orders*') || Request::is('admin/table/order*') || Request::is('admin/verify-offline-payment*') ? 'block' : 'none' }};">
                                    <li class="nav-item {{ Request::is('admin/table/order/running') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.table.order.running') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate sidebar--badge-container">
                                                {{ translate('Active Orders') }}
                                                {{-- Active = same filter as the page itself uses:
                                                     dine-in NOT paid + non-terminal status, OR
                                                     take-away/delivery/pos with non-terminal status.
                                                     Branch-scoped admins see their own branch only. --}}
                                                @php
                                                    $sbTerminal = ['completed', 'delivered', 'canceled', 'failed', 'refunded', 'refund_requested'];
                                                    $sbBranch = auth('admin')->user()?->branch_id;
                                                    $sbCount = \App\Model\Order::query()
                                                        ->where(function ($q) use ($sbTerminal) {
                                                            $q->where(function ($qq) use ($sbTerminal) {
                                                                $qq->where('order_type', 'dine_in')
                                                                   ->where('payment_status', '!=', 'paid')
                                                                   ->whereNotIn('order_status', $sbTerminal);
                                                            })->orWhere(function ($qq) use ($sbTerminal) {
                                                                $qq->whereIn('order_type', ['pos', 'take_away', 'delivery'])
                                                                   ->whereNotIn('order_status', $sbTerminal);
                                                            });
                                                        })
                                                        ->when($sbBranch, fn ($q, $b) => $q->where('branch_id', $b))
                                                        ->count();
                                                @endphp
                                                <span class="badge badge-soft-success badge-pill ml-1">{{ $sbCount }}</span>
                                            </span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/pos/orders*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.pos.orders') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate sidebar--badge-container">
                                                {{ translate('In-Restaurant') }}
                                                <span class="badge badge-soft-info badge-pill ml-1">
                                                    {{ \App\Model\Order::whereIn('order_type', ['pos', 'dine_in'])->count() }}
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                    {{-- Defensive: only render if route is registered. A stale
                                         routes cache without this entry would otherwise throw
                                         RouteNotFoundException and 500 every admin page. --}}
                                    @if(Route::has('admin.kitchen.scan.index'))
                                    <li class="nav-item {{ Request::is('admin/kitchen/scan*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.kitchen.scan.index') }}" target="_blank">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate sidebar--badge-container">
                                                {{ translate('Kitchen Scan') }}
                                                @php
                                                    $cookingCount = 0;
                                                    try {
                                                        $sbKitchenBranch = auth('admin')->user()?->branch_id;
                                                        $cookingCount = \App\Model\Order::query()
                                                            ->where('order_status', 'cooking')
                                                            ->when($sbKitchenBranch, fn ($q, $b) => $q->where('branch_id', $b))
                                                            ->count();
                                                    } catch (\Throwable $e) {
                                                        // Schema mid-migration or table missing — fail soft.
                                                    }
                                                @endphp
                                                @if($cookingCount > 0)
                                                    <span class="badge badge-soft-warning badge-pill ml-1">{{ $cookingCount }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    </li>
                                    @endif
                                    <li class="nav-item {{ Request::is('admin/cash-handovers*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.cash-handovers.index') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate sidebar--badge-container">
                                                {{ translate('Cash Collect') }}
                                                @php
                                                    $sbCashBranch = auth('admin')->user()?->branch_id;
                                                    $pendingHandovers = \App\Models\CashHandover::query()
                                                        ->where('status', 'pending')
                                                        ->when($sbCashBranch, fn ($q, $b) => $q->where('branch_id', $b))
                                                        ->count();
                                                @endphp
                                                @if($pendingHandovers > 0)
                                                    <span class="badge badge-soft-warning badge-pill ml-1">{{ $pendingHandovers }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    </li>
                                    {{-- Attendance + Shifts moved to dedicated HRM group below.
                                         Their links live there now — order-flow context only
                                         keeps the operational items (active orders, KOT scan,
                                         cash collect, online orders, offline payment). --}}
                                    <li class="nav-item {{ Request::is('admin/orders/list/*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.orders.list', ['all']) }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate sidebar--badge-container">
                                                {{ translate('Online Orders') }}
                                                <span class="badge badge-soft-info badge-pill ml-1">
                                                    {{ \App\Model\Order::notPos()->notDineIn()->notSchedule()->count() }}
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/verify-offline-payment*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.verify-offline-payment', ['pending']) }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Verify Offline Payment') }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        {{-- 4. Menu --}}
                        @if(Helpers::module_permission_check(MANAGEMENT_SECTION['product_management']))
                            <li class="navbar-vertical-aside-has-menu
                                {{ Request::is('admin/product*') || Request::is('admin/category*') || Request::is('admin/cuisine*') || Request::is('admin/addon*') || Request::is('admin/table/*') || Request::is('admin/promotion*') || Request::is('admin/banner*') || Request::is('admin/coupon*') || Request::is('admin/reviews*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Menu') }}">
                                    <i class="tio-restaurant nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Menu') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                    style="display: {{ Request::is('admin/product*') || Request::is('admin/category*') || Request::is('admin/cuisine*') || Request::is('admin/addon*') || Request::is('admin/table/*') || Request::is('admin/promotion*') || Request::is('admin/banner*') || Request::is('admin/coupon*') || Request::is('admin/reviews*') ? 'block' : 'none' }};">
                                    <li class="nav-item {{ Request::is('admin/product*') && !Request::is('admin/product/bulk*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.product.list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Products') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/category') || Request::is('admin/category/add') || Request::is('admin/category/edit*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.category.add') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Categories') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/category/add-sub-category*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.category.add-sub-category') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Sub Categories') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/cuisine*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.cuisine.add') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Cuisines') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/addon*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.addon.add-new') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Addons') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/table/list') || Request::is('admin/table/update*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.table.list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Tables') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/table/index') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.table.index') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Table Availability') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/promotion*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.promotion.create') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Promotion Setup') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/banner*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.banner.list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Banners') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/coupon*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.coupon.add-new') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Coupons') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/reviews*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.reviews.list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Reviews') }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        {{-- 5. People --}}
                        @if(Helpers::module_permission_check(MANAGEMENT_SECTION['user_management']))
                            <li class="navbar-vertical-aside-has-menu
                                {{ Request::is('admin/customer*') || Request::is('admin/delivery-man*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('People') }}">
                                    <i class="tio-user nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('People') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                    style="display: {{ Request::is('admin/customer*') || Request::is('admin/delivery-man*') ? 'block' : 'none' }};">
                                    <li class="nav-item {{ Request::is('admin/customer/list') || Request::is('admin/customer/view*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.customer.list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Customers') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/customer/wallet*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.customer.wallet.report') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Customer Wallet') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/customer/loyalty*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.customer.loyalty-point.report') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Loyalty Points') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/delivery-man*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.delivery-man.list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Delivery Men') }}</span>
                                        </a>
                                    </li>
                                    {{-- Chef + Employees + Employee Roles moved to the
                                         dedicated HRM group below. People keeps only
                                         customer-facing relationships (Customers, Wallet,
                                         Loyalty, Delivery Men). --}}
                                </ul>
                            </li>
                        @endif

                        {{-- 5b. HRM — staff scheduling, attendance, payroll-prep.
                             Gated via the new `hrm_management` module key so
                             Master Admin can grant per-role access from the
                             Employee Role form. Master Admin always sees it
                             (the helper short-circuits on admin_role_id == 1).
                             Sensitive items inside (Employees / Roles / HRM
                             Settings hub) keep their additional Master-only
                             check on top. --}}
                        @php
                            // HRM group visible if ANY of the four sub-modules is granted.
                            // Master Admin always passes via the helper short-circuit.
                            $hrmAttendance = Helpers::module_permission_check(MANAGEMENT_SECTION['hrm_attendance']);
                            $hrmEmployees  = Helpers::module_permission_check(MANAGEMENT_SECTION['hrm_employees']);
                            $hrmPayroll    = Helpers::module_permission_check(MANAGEMENT_SECTION['hrm_payroll']);
                            $hrmSettings   = Helpers::module_permission_check(MANAGEMENT_SECTION['hrm_settings']);
                            $staffNotices  = Helpers::module_permission_check(MANAGEMENT_SECTION['staff_notices']);
                            $checklists    = Helpers::module_permission_check(MANAGEMENT_SECTION['checklists']);
                            $hrmAny        = $hrmAttendance || $hrmEmployees || $hrmPayroll || $hrmSettings || $staffNotices || $checklists;
                        @endphp
                        @if($hrmAny)
                        <li class="navbar-vertical-aside-has-menu
                            {{ Request::is('admin/attendance*') || Request::is('admin/shifts*') || Request::is('admin/payroll*') || Request::is('admin/payroll-runs*') || Request::is('admin/biometric*') || Request::is('admin/salary-advances*') || Request::is('admin/salary-components*') || Request::is('admin/leaves*') || Request::is('admin/departments*') || Request::is('admin/designations*') || Request::is('admin/org-chart*') || Request::is('admin/hrm-settings*') || Request::is('admin/employee*') || Request::is('admin/custom-role*') || Request::is('admin/kitchen*') || Request::is('admin/staff-notice*') || Request::is('admin/checklist*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('HRM') }}">
                                <i class="tio-briefcase-outlined nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('HRM') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('admin/attendance*') || Request::is('admin/shifts*') || Request::is('admin/payroll*') || Request::is('admin/payroll-runs*') || Request::is('admin/biometric*') || Request::is('admin/salary-advances*') || Request::is('admin/salary-components*') || Request::is('admin/leaves*') || Request::is('admin/departments*') || Request::is('admin/designations*') || Request::is('admin/org-chart*') || Request::is('admin/hrm-settings*') || Request::is('admin/employee*') || Request::is('admin/custom-role*') || Request::is('admin/kitchen*') || Request::is('admin/staff-notice*') || Request::is('admin/checklist*') ? 'block' : 'none' }};">

                                {{-- Attendance ledger — open to all admins so staff can self-clock. --}}
                                @if(Route::has('admin.attendance.index') && $hrmAttendance)
                                <li class="nav-item {{ Request::is('admin/attendance*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.attendance.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Attendance') }}
                                            @php
                                                $sbAttBranch = auth('admin')->user()?->branch_id;
                                                $onDuty = 0;
                                                try {
                                                    $onDuty = \App\Models\AttendanceLog::query()
                                                        ->whereNull('clock_out_at')
                                                        ->when($sbAttBranch, fn ($q, $b) => $q->where('branch_id', $b))
                                                        ->count();
                                                } catch (\Throwable $e) { /* schema mid-migration */ }
                                            @endphp
                                            @if($onDuty > 0)
                                                <span class="badge badge-soft-success badge-pill ml-1">{{ $onDuty }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                                @endif

                                {{-- Leave management — every admin can file
                                     requests for themselves; managers see a
                                     pending-approvals badge so leave doesn't
                                     pile up unreviewed. --}}
                                @if(Route::has('admin.leaves.index') && $hrmAttendance)
                                <li class="nav-item {{ Request::is('admin/leaves*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.leaves.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Leaves') }}
                                            @php
                                                $sbLvViewer = auth('admin')->user();
                                                $sbLvBranch = $sbLvViewer?->branch_id;
                                                $pendingLv  = 0;
                                                try {
                                                    $pendingLv = \App\Models\LeaveRequest::query()
                                                        ->where('status', 'pending')
                                                        ->where('admin_id', '!=', $sbLvViewer?->id)
                                                        ->when($sbLvBranch, fn ($q, $b) => $q->where('branch_id', $b))
                                                        ->count();
                                                } catch (\Throwable $e) { /* schema mid-migration */ }
                                            @endphp
                                            @if($pendingLv > 0)
                                                <span class="badge badge-soft-warning badge-pill ml-1">{{ $pendingLv }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                                @endif

                                {{-- HRM Phase 6 — Org structure: Departments,
                                     Designations, Org Chart. Departments + Org
                                     Chart are open to all admins (read-only for
                                     branch managers). Designations + HRM Settings
                                     are Master-Admin-only. --}}
                                @if(Route::has('admin.org-chart.index') && $hrmEmployees)
                                <li class="nav-item {{ Request::is('admin/org-chart*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.org-chart.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Org Chart') }}</span>
                                    </a>
                                </li>
                                @endif

                                {{-- Departments / Designations / Salary Components /
                                     Employee Roles all live as tabs inside the
                                     "HRM Settings" hub at the bottom of this menu —
                                     keeps the sidebar focused on daily-use items. --}}

                                {{-- Payroll Estimate — read-only live computation.
                                     Used to sanity-check before creating a real run. --}}
                                @if(Route::has('admin.payroll.index') && $hrmPayroll)
                                <li class="nav-item {{ Request::is('admin/payroll') || Request::is('admin/payroll/employee*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.payroll.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Payroll Estimate') }}</span>
                                    </a>
                                </li>
                                @endif

                                {{-- Payroll Runs — actual locked records. Master Admin only. --}}
                                @if(Route::has('admin.payroll-runs.index') && $hrmPayroll)
                                <li class="nav-item {{ Request::is('admin/payroll-runs*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.payroll-runs.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Payroll Runs') }}
                                            @php
                                                $sbRunBranch = auth('admin')->user()?->branch_id;
                                                $draftRuns = 0;
                                                try {
                                                    $draftRuns = \App\Models\PayrollRun::query()
                                                        ->where('status', 'draft')
                                                        ->when($sbRunBranch, fn ($q, $b) => $q->where('branch_id', $b))
                                                        ->count();
                                                } catch (\Throwable $e) { /* schema mid-migration */ }
                                            @endphp
                                            @if($draftRuns > 0)
                                                <span class="badge badge-soft-warning badge-pill ml-1">{{ $draftRuns }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                                @endif

                                {{-- Salary advances / loans. Master Admin only. --}}
                                @if(Route::has('admin.salary-advances.index') && $hrmPayroll)
                                <li class="nav-item {{ Request::is('admin/salary-advances*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.salary-advances.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Advances') }}
                                            @php
                                                $sbAdvBranch = auth('admin')->user()?->branch_id;
                                                $activeAdv = 0;
                                                try {
                                                    $activeAdv = \App\Models\SalaryAdvance::query()
                                                        ->where('status', 'active')
                                                        ->when($sbAdvBranch, fn ($q, $b) => $q->where('branch_id', $b))
                                                        ->count();
                                                } catch (\Throwable $e) { /* schema mid-migration */ }
                                            @endphp
                                            @if($activeAdv > 0)
                                                <span class="badge badge-soft-warning badge-pill ml-1">{{ $activeAdv }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                                @endif

                                {{-- Biometric (ZKTeco) CSV import. Master Admin only since
                                     a bad CSV could distort everyone's attendance. --}}
                                @if(Route::has('admin.biometric.index') && $hrmPayroll)
                                <li class="nav-item {{ Request::is('admin/biometric*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.biometric.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Biometric Import') }}</span>
                                    </a>
                                </li>
                                @endif

                                {{-- Shift sessions — open/close drawer + variance ledger. --}}
                                @if(Route::has('admin.shifts.index') && $hrmAttendance)
                                <li class="nav-item {{ Request::is('admin/shifts*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.shifts.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Shifts') }}
                                            @php
                                                $sbShiftBranch = auth('admin')->user()?->branch_id;
                                                $openShifts = 0;
                                                try {
                                                    $openShifts = \App\Models\Shift::query()
                                                        ->where('status', 'open')
                                                        ->when($sbShiftBranch, fn ($q, $b) => $q->where('branch_id', $b))
                                                        ->count();
                                                } catch (\Throwable $e) { /* schema mid-migration */ }
                                            @endphp
                                            @if($openShifts > 0)
                                                <span class="badge badge-soft-success badge-pill ml-1">{{ $openShifts }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                                @endif

                                {{-- Staff directory. Chef list (admin.kitchen.list)
                                     route is preserved but no longer surfaced here —
                                     chefs are just employees with Kitchen department.
                                     Employee Roles moved to HRM Settings hub below. --}}
                                @if($hrmEmployees)
                                    <li class="nav-item {{ Request::is('admin/employee*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.employee.list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Employees') }}</span>
                                        </a>
                                    </li>
                                @endif

                                {{-- HRM Settings hub — General + Departments +
                                     Designations + Salary Components + Roles all
                                     live behind one entry, accessed via tab nav
                                     on the page itself. --}}
                                @if(Route::has('admin.hrm-settings.index') && $hrmSettings)
                                    <li class="nav-item {{ Request::is('admin/hrm-settings*') || Request::is('admin/departments*') || Request::is('admin/designations*') || Request::is('admin/salary-components*') || Request::is('admin/custom-role*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.hrm-settings.index') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('HRM Settings') }}</span>
                                        </a>
                                    </li>
                                @endif

                                {{-- My Lahab — staff notice board (publishes
                                     to the Flutter app + FCM push). --}}
                                @if(Route::has('admin.staff-notice.list') && $staffNotices)
                                    <li class="nav-item {{ Request::is('admin/staff-notice*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.staff-notice.list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Staff Notices') }}</span>
                                        </a>
                                    </li>
                                @endif

                                {{-- My Lahab — daily work assignments (open/close/daily). --}}
                                @if(Route::has('admin.checklist.list') && $checklists)
                                    <li class="nav-item {{ Request::is('admin/checklist*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.checklist.list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Work Assignments') }}</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        {{-- 5c. Accounts — cash ledger, transactions, daily fund,
                             suppliers, bills, expense categories. Gated via the
                             new `accounts_management` module key so Master Admin
                             can grant per-role access from the Employee Role form. --}}
                        @php
                            // Accounts group visible if either sub-module is granted.
                            $accountsDailyOps = Helpers::module_permission_check(MANAGEMENT_SECTION['accounts_daily_ops']);
                            $accountsBills    = Helpers::module_permission_check(MANAGEMENT_SECTION['accounts_bills']);
                            $accountsAny      = $accountsDailyOps || $accountsBills;
                        @endphp
                        @if($accountsAny)
                        <li class="navbar-vertical-aside-has-menu
                            {{ Request::is('admin/cash-accounts*') || Request::is('admin/account-transactions*') || Request::is('admin/daily-fund*') || Request::is('admin/expenses*') || Request::is('admin/suppliers*') || Request::is('admin/expense-categories*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Accounts') }}">
                                <i class="tio-money nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Accounts') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('admin/cash-accounts*') || Request::is('admin/account-transactions*') || Request::is('admin/daily-fund*') || Request::is('admin/expenses*') || Request::is('admin/suppliers*') || Request::is('admin/expense-categories*') ? 'block' : 'none' }};">

                                @if(Route::has('admin.daily-fund.index') && $accountsDailyOps)
                                <li class="nav-item {{ Request::is('admin/daily-fund*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.daily-fund.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Daily Fund Report') }}</span>
                                    </a>
                                </li>
                                @endif

                                @if(Route::has('admin.account-transactions.index') && $accountsDailyOps)
                                <li class="nav-item {{ Request::is('admin/account-transactions*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.account-transactions.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Transactions') }}</span>
                                    </a>
                                </li>
                                @endif

                                @if(Route::has('admin.cash-accounts.index') && $accountsDailyOps)
                                <li class="nav-item {{ Request::is('admin/cash-accounts*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.cash-accounts.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Cash Accounts') }}</span>
                                    </a>
                                </li>
                                @endif

                                {{-- Phase 8.6 — Bills & supplier master.
                                     Open to all admins (branch-scoped); each branch
                                     manages its own bills + sees consolidated
                                     suppliers (HQ-wide + their branch). --}}
                                @if(Route::has('admin.expenses.index') && $accountsBills)
                                <li class="nav-item {{ Request::is('admin/expenses*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.expenses.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Bills') }}
                                            @php
                                                $sbBranchId = auth('admin')->user()?->branch_id;
                                                $unpaidBills = 0;
                                                try {
                                                    $unpaidBills = \App\Models\Expense::query()
                                                        ->whereIn('status', ['pending', 'partial'])
                                                        ->when($sbBranchId, fn ($q, $b) => $q->where('branch_id', $b))
                                                        ->count();
                                                } catch (\Throwable $e) { /* schema mid-migration */ }
                                            @endphp
                                            @if($unpaidBills > 0)
                                                <span class="badge badge-soft-warning badge-pill ml-1">{{ $unpaidBills }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                                @endif

                                @if(Route::has('admin.suppliers.index') && $accountsBills)
                                <li class="nav-item {{ Request::is('admin/suppliers*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.suppliers.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Suppliers') }}</span>
                                    </a>
                                </li>
                                @endif

                                @if(Route::has('admin.expense-categories.index') && $accountsBills)
                                <li class="nav-item {{ Request::is('admin/expense-categories*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.expense-categories.index') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('Expense Categories') }}</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        {{-- 6. Reports --}}
                        @if(Helpers::module_permission_check(MANAGEMENT_SECTION['report_and_analytics_management']))
                            <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/report*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Reports') }}">
                                    <i class="tio-chart-bar-1 nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Reports') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                    style="display: {{ Request::is('admin/report*') ? 'block' : 'none' }};">
                                    <li class="nav-item {{ Request::is('admin/report/earning*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.report.earning') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Earning') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/report/order*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.report.order') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Orders') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/report/sale*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.report.sale-report') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Sales') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/report/product*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.report.product-report') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Products') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('admin/report/day-end*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.report.day-end') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Day-End Report') }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        @endif

                        {{-- Divider --}}
                        <li class="nav-item" style="margin:8px 20px;border-top:1px solid #eceef0;"></li>

                        {{-- Quick-access: Messages + Send Notification (daily tools) --}}
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/message*') ? 'show' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.message.list') }}" title="{{ translate('Messages') }}">
                                <i class="tio-comment nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Messages') }}</span>
                            </a>
                        </li>

                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/notification*') ? 'show' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.notification.add-new') }}" title="{{ translate('Send Notification') }}">
                                <i class="tio-notifications nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Send Notification') }}</span>
                            </a>
                        </li>

                        {{-- Settings — everything rare goes here --}}
                        @if(auth('admin')->user()?->admin_role_id == 1)
                            <li class="navbar-vertical-aside-has-menu
                                {{ Request::is('admin/business-settings*') || Request::is('admin/branch*') || Request::is('admin/ai-settings*') || Request::is('admin/system-addon*') || Request::is('admin/table/promotion*') || Request::is('admin/branch/attendance-qr-posters*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Settings') }}">
                                    <i class="tio-settings nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Settings') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                    style="display: {{ Request::is('admin/business-settings*') || Request::is('admin/branch*') || Request::is('admin/ai-settings*') || Request::is('admin/system-addon*') || Request::is('admin/branch/attendance-qr-posters*') ? 'block' : 'none' }};">
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.restaurant.restaurant-setup') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Business Setup') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.branch.list') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Branches') }}</span></a></li>
                                    {{-- Branch attendance QR posters live next to the
                                         branch list because they're a per-branch artifact. --}}
                                    @if(Route::has('admin.branch.attendance-qr-posters'))
                                        <li class="nav-item {{ Request::is('admin/branch/attendance-qr-posters*') ? 'active' : '' }}"><a class="nav-link" href="{{ route('admin.branch.attendance-qr-posters') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Attendance QR Posters') }}</span></a></li>
                                    @endif
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.email-setup', ['user']) }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Email Templates') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.page-setup.about-us') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Pages & Policies') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.web-app.third-party.social-media') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Social Media') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.web-app.payment-method') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Third Party') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.web-app.third-party.offline-payment.list') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Offline Payment Methods') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.web-app.third-party.fcm-index') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Firebase / FCM') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.web-app.printer.index') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Receipt Printer') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.ai-settings.configuration') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('AI Configuration') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.web-app.system-setup.language.index') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('System Setup') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.system-addon.index') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('System Addons') }}</span></a></li>
                                </ul>
                            </li>
                        @endif

                    </ul>

                </div>
            </div>
        </div>
    </aside>
</div>

{{-- Empty placeholder — custom.js's HSDemo() reads .innerHTML from this id at load.
     Removing it throws TypeError and breaks the theme's entire JS init. --}}
<div id="sidebarCompact" class="d-none"></div>

@push('script_2')
    <script>
        'use strict';
        // Auto-scroll the active item into view on load.
        $(window).on('load', function () {
            if ($(".navbar-vertical-content li.active").length) {
                $('.navbar-vertical-content').animate({
                    scrollTop: $(".navbar-vertical-content li.active").offset().top - 150
                }, 500);
            }
        });
    </script>
@endpush
