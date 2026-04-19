@extends('layouts.admin.app')

@section('title', translate('Update banner'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/banner.png')}}" alt="">
                <span class="page-header-title">
                    {{translate('Update_Banner')}}
                </span>
            </h2>
        </div>

        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <form action="{{route('admin.banner.update',[$banner['id']])}}" method="post" enctype="multipart/form-data">
                    @csrf @method('put')

                    <div class="">
                        <div class="card-body p-0">
                            <div class="row g-2">
                                <div class="col-lg-8">
                                    <div class="card p-4 h-100">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('title')}}<span class="text-danger ml-1">*</span></label>
                                            <input type="text" name="title" value="{{$banner['title']}}" class="form-control"
                                                placeholder="{{translate('New banner')}}" tabindex="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="input-label">{{translate('item_Type')}}<span class="text-danger ml-1">*</span></label>
                                            <select name="item_type" class="custom-select" tabindex="2">
                                                <option value="product" {{$banner['product_id']==null?'':'selected'}}>{{translate('product')}}</option>
                                                <option value="category" {{$banner['category_id']==null?'':'selected'}}>{{translate('category')}}</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-0" id="type-product" style="display: {{$banner['product_id']==null?'none':'block'}}">
                                            <label class="input-label">{{translate('product')}} <span class="text-danger ml-1">*</span></label>
                                            <select name="product_id" class="custom-select" tabindex="3">
                                                @foreach($products as $product)
                                                    <option value="{{$product['id']}}" {{$banner['product_id']==$product['id']?'selected':''}}>{{$product['name']}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group mb-0" id="type-category" style="display: {{$banner['category_id']==null?'none':'block'}}">
                                            <label class="input-label">{{translate('category')}} <span class="text-danger ml-1">*</span></label>
                                            <select name="category_id" class="form-control js-select2-custom" tabindex="4">
                                                @foreach($categories as $category)
                                                    <option value="{{$category['id']}}" {{$banner['category_id']==$category['id']?'selected':''}}>{{$category['name']}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="d-center card p-4 h-100">
                                        <div class="">
                                            <div class="mb-4">
                                                <h4 class="mb-0 text-center">{{ translate('banner_Image') }} </h4>
                                            </div>
                                            <div class="text-center">
                                                <div class="upload-file_custom ratio-2-1 h-100px mx-auto">
                                                    <input type="file" name="image"
                                                            class="upload-file__input single_file_input"
                                                            accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                            data-maxFileSize="{{ readableUploadMaxFileSize('image') }}">
                                                    <label class="upload-file__wrapper w-100 h-100 m-0">
                                                        <div class="upload-file-textbox text-center" style="">
                                                            <img class="svg" src="{{asset('public/assets/admin/img/document-upload.svg')}}" alt="img">
                                                            <h6 class="mt-1 tc-clr fw-medium fs-10 lh-base text-center">
                                                                <span class="text-c2">{{ translate('Click to upload') }}</span>
                                                                <br>
                                                                {{ translate('Or drag and drop') }}
                                                            </h6>
                                                        </div>
                                                        <img class="upload-file-img" loading="lazy" src="{{$banner->imageFullPath}}"
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
                                <button type="reset" class="btn btn-secondary cmn_focus" tabindex="6">{{translate('reset')}}</button>
                                <button type="submit" class="btn btn-primary cmn_focus" tabindex="6">{{translate('update')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        $("select[name='item_type']").change(function() {
            var selectedValue = $(this).val();
            show_item(selectedValue);
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
    </script>
@endpush
