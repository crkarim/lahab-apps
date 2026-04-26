<div id="headerMain" class="d-none">
    <header id="header" class="navbar navbar-expand-lg navbar-fixed navbar-height navbar-flush navbar-container navbar-bordered">
        <div class="navbar-nav-wrap">
            <div class="navbar-brand-wrapper">
                <!-- Logo -->
                @php($restaurant_logo=\App\Model\BusinessSetting::where(['key'=>'logo'])->first()->value)
                <a class="navbar-brand" href="{{route('admin.dashboard')}}" aria-label="">
                    <img class="navbar-brand-logo" style="object-fit: contain;"
                         onerror="this.src='{{asset('public/assets/admin/img/160x160/img1.jpg')}}'"
                         src="{{asset('storage/app/public/restaurant/'.$restaurant_logo)}}" alt="Logo">
                    <img class="navbar-brand-logo-mini" style="object-fit: contain;"
                         onerror="this.src='{{asset('public/assets/admin/img/160x160/img1.jpg')}}'"
                         src="{{asset('storage/app/public/restaurant/'.$restaurant_logo)}}"
                         alt="Logo">
                </a>
                <!-- End Logo -->
            </div>

            <div class="navbar-nav-wrap-content-left d-xl-none">
                <!-- Navbar Vertical Toggle -->
                <button type="button" class="js-navbar-vertical-aside-toggle-invoker close mr-3">
                    <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip"
                       data-placement="right" title="Collapse"></i>
                    <i class="tio-last-page navbar-vertical-aside-toggle-full-align"
                       data-template='<div class="tooltip d-none d-sm-block" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'
                       data-toggle="tooltip" data-placement="right" title="Expand"></i>
                </button>
                <!-- End Navbar Vertical Toggle -->
            </div>

            <!-- Secondary Content -->
            <div class="navbar-nav-wrap-content-right">
                <!-- Navbar -->
                <ul class="navbar-nav align-items-center flex-row">

                    {{-- Live counts for the Active Orders button. Two buckets:
                         "urgent" = pending/confirmed (must be sent to the
                         kitchen — drives the red pulse), "in flight" = food
                         is being cooked or waiting for the next operator
                         action. Both update on every page load; cheap because
                         it's one indexed query. --}}
                    @php
                        $activeUrgent  = \App\Model\Order::whereIn('order_status', ['pending', 'confirmed'])->count();
                        $activeInFlight = \App\Model\Order::whereIn('order_status', ['cooking', 'done', 'processing', 'out_for_delivery'])->count();
                        $activeTotal   = $activeUrgent + $activeInFlight;
                    @endphp
                    <li class="nav-item d-none d-sm-inline-block mr-2">
                        <a href="{{ route('admin.table.order.running') }}"
                           class="lh-header-action lh-header-active-orders {{ $activeUrgent > 0 ? 'lh-needs-action' : '' }}"
                           title="{{ translate('Active Orders') }} — {{ $activeUrgent }} {{ translate('to send') }}, {{ $activeInFlight }} {{ translate('in progress') }}">
                            <i class="tio-shopping-cart"></i>
                            <span class="lh-header-label">{{ translate('Active Orders') }}</span>
                            @if($activeUrgent > 0)
                                <span class="lh-header-badge lh-header-badge-urgent">{{ $activeUrgent }}</span>
                            @endif
                            @if($activeInFlight > 0)
                                <span class="lh-header-badge lh-header-badge-flight">{{ $activeInFlight }}</span>
                            @endif
                            @if($activeTotal === 0)
                                <span class="lh-header-badge lh-header-badge-idle">0</span>
                            @endif
                        </a>
                    </li>

                    @if(\App\CentralLogics\Helpers::module_permission_check(MANAGEMENT_SECTION['pos_management']))
                        <li class="nav-item d-none d-sm-inline-block mr-2">
                            <a href="{{ route('admin.pos.index') }}"
                               class="lh-header-action lh-header-new-sale"
                               title="{{ translate('Start a new POS sale') }}">
                                <i class="tio-add-circle-outlined"></i>
                                <span class="lh-header-label">{{ translate('New Sale') }}</span>
                            </a>
                        </li>
                    @endif

                    <li class="nav-item d-none d-md-inline-block mr-2">
                        <button type="button"
                                class="btn btn-sm d-flex align-items-center gap-2"
                                style="background:#f2f2f7;border:1px solid #e5e5ea;color:#555;height:34px;padding:0 10px;border-radius:8px;"
                                onclick="document.dispatchEvent(new KeyboardEvent('keydown',{key:'k',metaKey:true}))"
                                title="{{ translate('Quick search (⌘K / Ctrl+K)') }}">
                            <i class="tio-search" style="font-size:14px;"></i>
                            <span style="color:#888;font-size:12px;">{{ translate('Search') }}</span>
                            <span style="display:inline-flex;gap:2px;">
                                <kbd style="background:#fff;border:1px solid #d8d8dd;border-radius:4px;padding:0 4px;font-size:10px;line-height:18px;color:#6a6a70;">⌘</kbd>
                                <kbd style="background:#fff;border:1px solid #d8d8dd;border-radius:4px;padding:0 4px;font-size:10px;line-height:18px;color:#6a6a70;">K</kbd>
                            </span>
                        </button>
                    </li>

                    <li class="nav-item d-none d-sm-inline-block">
                        <div class="hs-unfold">
                            <div class="bg-white p-1 rounded">
                                @php( $local = session()->has('local')?session('local'):'en')
{{--                                @php($lang = \App\CentralLogics\Helpers::get_business_settings('language')??null)--}}
                                <?php
                                $languages = \App\Model\BusinessSetting::where('key', 'language')->first();
                                $lang = json_decode($languages->value, true);
                                ?>
                                <div class="topbar-text dropdown disable-autohide text-capitalize">
                                    @if(isset($lang) && array_key_exists('code', $lang[0]))
                                        <a class="topbar-link dropdown-toggle d-flex gap-2 align-items-center font-weight-bold dropdown-toggle-empty lang-country-flag" href="#" data-toggle="dropdown">
                                            @foreach($lang as $data)
                                                @if($data['code']==$local)
                                                    <img src="{{asset('public/assets/admin/img/google_translate_logo.png')}}" alt=""> <span>{{$data['name']}}</span>
                                                @endif
                                            @endforeach
                                        </a>
                                        <ul class="dropdown-menu">
                                            @foreach($lang as $key =>$data)
                                                @if($data['status']==1)
                                                    <li>
                                                        <a class="dropdown-item pr-8 d-flex gap-2 align-items-center"
                                                           href="{{route('admin.lang',[$data['code']])}}">
                                                            {{--<img class="avatar-img rounded-0" src="{{asset('public/assets/admin/img/flag.png')}}" alt="Image Description">--}}
                                                            <span class="text-capitalize">{{\App\CentralLogics\Helpers::get_language_name($data['code'])}}</span>
                                                        </a>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>

                    <li class="nav-item d-none d-sm-inline-block">
                        <!-- Notification -->
                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-icon btn-ghost-secondary rounded-circle"
                               href="{{route('admin.message.list')}}">
                                <i class="tio-messages-outlined"></i>
                                @php($message=\App\Model\Conversation::where('checked',0)->distinct('user_id')->count())
{{--                                @if($message!=0)--}}
                                    <span class="btn-status btn-status-c1">{{$message}}</span>
{{--                                @endif--}}
                            </a>
                        </div>
                        <!-- End Notification -->
                    </li>

                    <li class="nav-item d-none d-sm-inline-block">
                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-icon btn-ghost-secondary rounded-circle"
                               href="{{route('admin.orders.list',['status'=>'pending'])}}">
                                <i class="tio-shopping-cart-outlined"></i>
                                <span class="btn-status btn-status-c1">0</span>
                            </a>
                        </div>
                    </li>


                    <li class="nav-item ml-md-4 ml-sm-1 ml-0">
                        <!-- Account -->
                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker navbar-dropdown-account-wrapper media gap-2" href="javascript:;"
                               data-hs-unfold-options='{
                                     "target": "#accountNavbarDropdown",
                                     "type": "css-animation"
                                   }'>
                                <div class="media-body d-flex align-items-end flex-column">
                                    <span class="card-title h5">{{auth('admin')->user()->f_name}}</span>
                                    <span class="card-text fz-12 font-weight-bold">{{auth('admin')->user()->role->name}}</span>
                                </div>
                                <div class="avatar avatar-sm avatar-circle">
                                    <img class="avatar-img"
                                         onerror="this.src='{{asset('public/assets/admin/img/160x160/img1.jpg')}}'"
                                         src="{{asset('storage/app/public/admin')}}/{{auth('admin')->user()->image}}"
                                         alt="Image Description">
                                    <span class="avatar-status avatar-sm-status avatar-status-success"></span>
                                </div>
                            </a>

                            <div id="accountNavbarDropdown"
                                 class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-right navbar-dropdown-menu navbar-dropdown-account navbar-dropdown-lg">
                                <div class="dropdown-item-text">
                                    <div class="media align-items-center">
                                        <div class="avatar avatar-sm avatar-circle mr-2">
                                            <img class="avatar-img"
                                                 onerror="this.src='{{asset('public/assets/admin/img/160x160/img1.jpg')}}'"
                                                 src="{{asset('storage/app/public/admin')}}/{{auth('admin')->user()->image}}"
                                                 alt="Image Description">
                                        </div>
                                        <div class="media-body">
                                            <span class="card-title h5">{{auth('admin')->user()->f_name}}</span>
                                            <span class="card-text">{{auth('admin')->user()->email}}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="dropdown-divider"></div>

                                <a class="dropdown-item" href="{{route('admin.settings')}}">
                                    <span class="text-truncate pr-2" title="Settings">{{translate('settings')}}</span>
                                </a>

                                <div class="dropdown-divider"></div>

                                <a class="dropdown-item" href="javascript:" onclick="Swal.fire({
                                    title: '{{translate("Do you want to logout?")}}',
                                    showDenyButton: true,
                                    showCancelButton: true,
                                    confirmButtonColor: '#E67E22',
                                    cancelButtonColor: '#363636',
                                    confirmButtonText: '{{translate("Yes")}}',
                                    cancelButtonText: `{{translate('No')}}`,
                                    }).then((result) => {
                                    if (result.value) {
                                    location.href='{{route('admin.auth.logout')}}';
                                    } else{
                                        Swal.fire({
                                        title: '{{translate("Canceled")}}',
                                        confirmButtonText: '{{translate("Okay")}}',
                                        })
                                    }
                                    })">
                                    <span class="text-truncate pr-2" title="Sign out">{{translate('sign_out')}}</span>
                                </a>
                            </div>
                        </div>
                        <!-- End Account -->
                    </li>
                </ul>
                <!-- End Navbar -->
            </div>
            <!-- End Secondary Content -->
        </div>
    </header>
</div>
<div id="headerFluid" class="d-none"></div>
<div id="headerDouble" class="d-none"></div>
