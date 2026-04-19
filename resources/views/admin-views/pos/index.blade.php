@extends('layouts.admin.app')

@section('title', translate('New_Sale'))

@section('content')
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
            <div class="container-fluid">
                <div class="row gy-3 gx-2">
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="pos-title">
                                <h4 class="mb-0">{{translate('Product_Section')}}</h4>
                            </div>

                            <div class="d-flex flex-wrap flex-md-nowrap justify-content-between gap-3 gap-xl-4 px-4 py-4">
                                <div class="w-100 mr-xl-2">
                                    <select name="category" class="form-control js-select2-custom-x mx-1 category">
                                        <option value="">{{translate('All Categories')}}</option>
                                        @foreach ($categories as $item)
                                            <option value="{{$item->id}}" {{$category==$item->id?'selected':''}}>{{ Str::limit($item->name, 40)}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-100 ml-xl-2">
                                    <form id="search-form">
                                        <div class="input-group input-group-merge input-group-flush border rounded">
                                            <div class="input-group-prepend pl-2">
                                                <div class="input-group-text">
                                                    <img width="13" src="{{asset('public/assets/admin/img/icons/search.png')}}" alt="">
                                                </div>
                                            </div>
                                            <input id="datatableSearch" type="search" value="{{$keyword?$keyword:''}}" name="search" class="form-control border-0" placeholder="{{translate('Search here')}}" aria-label="Search here">
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body pt-0" id="items">
                                <div class="pos-item-wrap justify-content-center">

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

                    <div class="col-lg-5">
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
                                                        <input type="radio" class="order-type-radio" name="order_type" value="take_away" {{ !session()->has('order_type') || session()->get('order_type') == 'take_away' ? 'checked' : '' }}>
                                                        <span class="media align-items-center mb-0">
                                                        <span class="media-body">{{translate('Take Away')}}</span>
                                                    </span>
                                                    </label>

                                                    <label class="custom-radio d-flex align-items-center m-0">
                                                        <input type="radio" class="order-type-radio" name="order_type" value="dine_in" {{ session()->has('order_type') && session()->get('order_type') == 'dine_in' ? 'checked' : '' }}>
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

                                        <div class="d-none" id="dine_in_section">
                                            <div class="form-group mb-3 d-flex flex-wrap flex-sm-nowrap gap-2">
                                                <select name="table_id" class="js-select2-custom-x form-ellipsis form-control select-table">
                                                    <option disabled selected>{{translate('select_table')}}</option>
                                                    @foreach($tables as $table)
                                                        <option value="{{$table['id']}}" {{ session()->get('table_id') == $table['id'] ? 'selected' : '' }}>{{translate('table ')}} - {{$table['number']}}</option>
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


        @php($order=\App\Model\Order::find(session('last_order')))
        @if($order)
            @php(session(['last_order'=> false]))
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

                                    @php($googleMapStatus = \App\CentralLogics\Helpers::get_business_settings('google_map_status'))
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
@endsection

@push('script_2')

    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
    <script src="{{asset('public/assets/admin')}}/js/vendor.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/js/theme.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/js/sweet_alert.js"></script>
    <script src="{{asset('public/assets/admin')}}/js/toastr.js"></script>
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
            select_order_type(selectedOrderType);
        });

        $('.select-table').change(function() {
            var selectedTableId = $(this).val();
            store_key('table_id', selectedTableId);
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
                confirmButtonColor: '#FC6A57',
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

        $(document).on('ready', function () {
            @if($order)
            $('#print-invoice').modal('show');
            @endif
        });

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
                                confirmButtonColor: '#FC6A57',
                                icon: 'info',
                                title: '{{translate("Cart")}}',
                                confirmButtonText:'{{translate("Ok")}}',
                                text: "{{translate('Product already added in cart')}}"
                            });
                            return false;
                        } else if (data.data == 0) {
                            Swal.fire({
                                confirmButtonColor: '#FC6A57',
                                icon: 'error',
                                title: '{{translate("Cart")}}',
                                confirmButtonText:'{{translate("Ok")}}',
                                text: '{{translate('Sorry, product out of stock')}}.'
                            });
                            return false;
                        }
                        else if (data.data == 'variation_error') {
                            Swal.fire({
                                confirmButtonColor: '#FC6A57',
                                icon: 'error',
                                title: 'Cart',
                                text: data.message
                            });
                            return false;
                        }
                        else if (data.data == 'stock_limit') {
                            Swal.fire({
                                confirmButtonColor: '#FC6A57',
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
                    confirmButtonColor: '#FC6A57',
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

            if (orderType === 'dine_in') {
                $('#dine_in_section').removeClass('d-none');
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
                var myLatLng = {

                    lat: 23.811842872190343,
                    lng: 90.356331
                };
                const map = new google.maps.Map(document.getElementById("location_map_canvas"), {
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
@endpush

