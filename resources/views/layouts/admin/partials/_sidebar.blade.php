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
                                                {{-- Count of every order still needing the operator's
                                                     attention — across dine-in, delivery, and
                                                     take-away. Mirrors the All-tab count on the
                                                     Active Orders page. --}}
                                                <span class="badge badge-soft-success badge-pill ml-1">
                                                    {{ \App\Model\Order::whereNotIn('order_status', ['completed', 'delivered', 'canceled', 'failed', 'refunded', 'refund_requested'])->count() }}
                                                </span>
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
                                {{ Request::is('admin/customer*') || Request::is('admin/delivery-man*') || Request::is('admin/employee*') || Request::is('admin/custom-role*') || Request::is('admin/kitchen*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('People') }}">
                                    <i class="tio-user nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('People') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                    style="display: {{ Request::is('admin/customer*') || Request::is('admin/delivery-man*') || Request::is('admin/employee*') || Request::is('admin/custom-role*') || Request::is('admin/kitchen*') ? 'block' : 'none' }};">
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
                                    <li class="nav-item {{ Request::is('admin/kitchen*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.kitchen.list') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate">{{ translate('Chef') }}</span>
                                        </a>
                                    </li>
                                    @if(auth('admin')->user()?->admin_role_id == 1)
                                        <li class="nav-item {{ Request::is('admin/employee*') ? 'active' : '' }}">
                                            <a class="nav-link" href="{{ route('admin.employee.list') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate('Employees') }}</span>
                                            </a>
                                        </li>
                                        <li class="nav-item {{ Request::is('admin/custom-role*') ? 'active' : '' }}">
                                            <a class="nav-link" href="{{ route('admin.custom-role.create') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate('Employee Roles') }}</span>
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
                                {{ Request::is('admin/business-settings*') || Request::is('admin/branch*') || Request::is('admin/ai-settings*') || Request::is('admin/system-addon*') || Request::is('admin/table/promotion*') ? 'active' : '' }}">
                                <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Settings') }}">
                                    <i class="tio-settings nav-icon"></i>
                                    <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Settings') }}</span>
                                </a>
                                <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                    style="display: {{ Request::is('admin/business-settings*') || Request::is('admin/branch*') || Request::is('admin/ai-settings*') || Request::is('admin/system-addon*') ? 'block' : 'none' }};">
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.restaurant.restaurant-setup') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Business Setup') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.branch.list') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Branches') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.email-setup', ['user']) }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Email Templates') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.page-setup.about-us') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Pages & Policies') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.web-app.third-party.social-media') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Social Media') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.web-app.payment-method') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Third Party') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.web-app.third-party.offline-payment.list') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Offline Payment Methods') }}</span></a></li>
                                    <li class="nav-item"><a class="nav-link" href="{{ route('admin.business-settings.web-app.third-party.fcm-index') }}"><span class="tio-circle nav-indicator-icon"></span><span>{{ translate('Firebase / FCM') }}</span></a></li>
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
