@extends('layouts.admin.app')

@section('title', translate('Banner list'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/banner.png')}}" alt="">
                <span class="page-header-title">
                    {{translate('Banner_Setup')}}
                </span>
            </h2>
        </div>

        <div class="row g-2">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <form action="{{route('admin.banner.store')}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class=" banner-form">
                        <div class="card-body p-0">
                            <div class="row g-2">
                                <div class="col-lg-8">
                                    <div class="card p-4 h-100">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('title')}}<span class="text-danger ml-1">*</span></label>
                                            <input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="{{translate('New banner')}}" tabindex="1" required>
                                        </div>

                                        <div class="form-group">
                                            <label class="input-label">{{translate('item_Type')}}<span class="text-danger ml-1">*</span></label>
                                            <select name="item_type" class="custom-select js-select2-custom" tabindex="2">
                                                <option selected disabled>{{translate('select_item_type')}}</option>
                                                <option value="product">{{translate('product')}}</option>
                                                <option value="category">{{translate('category')}}</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-0" id="type-product">
                                            <label class="input-label">{{translate('product')}} <span class="text-danger ml-1">*</span></label>
                                            <select name="product_id" class="custom-select js-select2-custom" tabindex="3">
                                                <option selected disabled>{{translate('select_a_product')}}</option>
                                                @foreach(\App\Model\Product::all() as $product)
                                                    <option value="{{$product['id']}}">{{$product['name']}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group mb-0 type-category" id="type-category">
                                            <label class="input-label">{{translate('category')}} <span class="text-danger ml-1">*</span></label>
                                            <select name="category_id" class="custom-select js-select2-custom" tabindex="4">
                                                <option selected disabled>{{translate('select_a_category')}}</option>
                                                @foreach(\App\Model\Category::where('parent_id', 0)->get() as $category)
                                                    <option value="{{$category['id']}}">{{$category['name']}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="d-center card p-4 h-100">
                                        <div class="">
                                            <div class="mb-4">
                                                <h4 class="mb-0 text-center">{{ translate('banner_Image') }} <span class="text-danger">*</span> </h4>
                                            </div>
                                            <div class="text-center">
                                                <div class="upload-file_custom ratio-2-1 h-100px mx-auto">
                                                    <input type="file" name="image"
                                                            class="upload-file__input single_file_input"
                                                            accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                            data-maxFileSize="{{ readableUploadMaxFileSize('image') }}" required>
                                                    <label class="upload-file__wrapper w-100 h-100 m-0">
                                                        <div class="upload-file-textbox text-center" style="">
                                                            <img class="svg" src="{{asset('public/assets/admin/img/document-upload.svg')}}" alt="img">
                                                            <h6 class="mt-1 tc-clr fw-medium fs-10 lh-base text-center">
                                                                <span class="text-c2">{{ translate('Click to upload') }}</span>
                                                                <br>
                                                                {{ translate('Or drag and drop') }}
                                                            </h6>
                                                        </div>
                                                        <img class="upload-file-img" loading="lazy" src=""
                                                                data-default-src="" alt="" style="display: none;">
                                                    </label>
                                                    <div class="overlay-review">
                                                        <div
                                                            class="d-flex gap-1 justify-content-center align-items-center h-100">
                                                            <button type="button"
                                                                    class="btn icon-btn view_btn">
                                                                <i class="tio-invisible"></i>
                                                            </button>
                                                            <button type="button"
                                                                    class="btn icon-btn edit_btn">
                                                                <i class="tio-edit"></i>
                                                            </button>
                                                            <button type="button" class="remove_btn btn icon-btn">
                                                                <i class="tio-delete text-danger"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="mb-0 fs-12 mt-20 text-center">
                                                {{ implode(', ', array_column(IMAGEEXTENSION, 'key')) }} {{ readableUploadMaxFileSize('image') }}
                                                <span class="font-medium text-title">(2:1)</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <button type="reset" id="reset" class="btn btn-secondary cmn_focus" tabindex="6">{{translate('reset')}}</button>
                                <button type="submit" class="btn btn-primary cmn_focus" tabindex="7">{{translate('submit')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-top px-card pt-4">
                        <div class="row align-items-center gy-2">
                            <div class="col-sm-4 col-md-6 col-lg-8">
                                <h5 class="d-flex align-items-center gap-2 mb-0">
                                    {{translate('Banner_List')}}
                                    <span class="badge badge-soft-dark rounded-50 fz-12">{{ $banners->total() }}</span>
                                </h5>
                            </div>
                            <div class="col-sm-8 col-md-6 col-lg-4">
                                <form action="{{ url()->current() }}" method="GET">
                                    <div class="input-group">
                                        <input id="datatableSearch_" type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{translate('Search_by_Title')}}" aria-label="Search" required="" autocomplete="off">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">
                                                {{translate('Search')}}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="py-4">
                        <div class="table-responsive datatable-custom">
                            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                                <thead class="thead-light">
                                <tr>
                                    <th>{{translate('SL')}}</th>
                                    <th>{{translate('Banner_Image')}}</th>
                                    <th>{{translate('Title')}}</th>
                                    <th>{{translate('Banner_Type')}}</th>
                                    <th>{{translate('status')}}</th>
                                    <th class="text-center">{{translate('action')}}</th>
                                </tr>
                                </thead>

                                <tbody>
                                @foreach($banners as $key=>$banner)
                                    <tr>
                                        <td>{{$banners->firstitem()+$key}}</td>
                                        <td>
                                            <img class="img-vertical-150" src="{{$banner->imageFullPath}}" alt="{{ translate('banner image') }}">
                                        </td>
                                        <td>
                                            <div class="max-w300 min-w-120px line-limit-2 text-wrap">
                                                {{$banner['title']}}
                                            </div>
                                        </td>
                                        @if(isset($banner->category_id))
                                            <td>{{translate('category')}}: {{substr(\App\Model\Category::find($banner->category_id)?->name, 0, 15)}}</td>
                                        @elseif(isset($banner->product_id))
                                            <td>{{translate('product')}}: {{ substr(\App\Model\Product::find($banner->product_id)?->name,0, 15) }}...</td>
                                        @else
                                            <td></td>
                                        @endif
                                        <td>
                                            <label class="switcher">
                                                <input class="switcher_input status-change" type="checkbox" {{$banner['status']==1 ? 'checked' : ''}} id="{{$banner['id']}}"
                                                    data-url="{{route('admin.banner.status',[$banner['id'],0])}}">
                                                <span class="switcher_control"></span>
                                            </label>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a class="btn btn-outline-info btn-sm edit square-btn"
                                                    href="{{route('admin.banner.edit',[$banner['id']])}}"><i class="tio-edit"></i></a>
                                                <button type="button" class="btn btn-outline-danger btn-sm delete square-btn form-alert" data-id="banner-{{$banner['id']}}" data-message="{{translate('Want to delete this banner')}}"
                                                    ><i class="tio-delete"></i></button>
                                            </div>
                                            <form action="{{route('admin.banner.delete',[$banner['id']])}}"
                                                method="post" id="banner-{{$banner['id']}}">
                                                @csrf @method('delete')
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="table-responsive mt-4 px-3">
                            <div class="d-flex justify-content-lg-end">
                                {!! $banners->links() !!}
                            </div>
                        </div>

                        @if(count($banners) == 0)
                            <div class="text-center p-4">
                                <img class="w-120px mb-3" src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="Image Description">
                                <p class="mb-0">{{translate('No_data_to_show')}}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        $('.js-select2-custom').each(function () {
            var select2 = $.HSCore.components.HSSelect2.init($(this));
        });

        $("select[name='item_type']").change(function() {
            var selectedValue = $(this).val();
            show_item(selectedValue);
        });

        $(".status-change").change(function() {
            var selectedValue = $(this).val();
            status_change(selectedValue);
        });

        function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    $('#viewer').attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function () {
            readURL(this);
        });


        function show_item(type) {
            if (type === 'product') {
                $("#type-product").show();
                $("#type-category").hide();
            } else {
                $("#type-product").hide();
                $("#type-category").show();
            }
        }

        $(".js-select2-custom").select2({
            placeholder: "Select a item",
            allowClear: true
        });
    </script>
@endpush
