@extends('layouts.branch.app')

@section('title', translate('Chef New Add'))

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
        <h2 class="h1 mb-0 d-flex align-items-center gap-2">
            <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/cooking.png')}}" alt="">
            <span class="page-header-title">
                {{translate('Add_New_Chef')}}
            </span>
        </h2>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{route('branch.kitchen.add-new')}}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="row">

                            <div class="col-md-8">
                                <div class="card p-4 h-100">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name">{{translate('First Name')}} <span class="text-danger">*</span></label>
                                            <input type="text" name="f_name" class="form-control" id="f_name"
                                                   placeholder="{{translate('Ex')}} : {{translate('John')}}" value="{{old('f_name')}}" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="name">{{translate('Last Name')}} <span class="text-danger">*</span></label>
                                            <input type="text" name="l_name" class="form-control" id="l_name"
                                                   placeholder="{{translate('Ex')}} : {{translate('Doe')}}" value="{{old('l_name')}}" required>
                                        </div>
                                    </div>
    
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="phone">{{translate('Phone')}} <span class="text-danger">*</span> {{translate('(with country code)')}}</label>
                                            <input type="text" name="phone" value="{{old('phone')}}" class="form-control" id="phone"
                                                   placeholder="{{translate('Ex')}} : +88017********" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="name">{{translate('Email')}} <span class="text-danger">*</span></label>
                                            <input type="email" name="email" value="{{old('email')}}" class="form-control" id="email"
                                                   placeholder="{{translate('Ex')}} : ex@gmail.com" required>
                                        </div>
                                    </div>
    
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="name">{{translate('password')}}<span class="text-danger">*</span> {{ translate('(minimum length will be 6 character)')}}</label>
                                            <input type="password" name="password" class="form-control" id="password" placeholder="{{translate('Password')}}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card d-center p-4 h-100">
                                    <div class="">
                                        <div class="mb-4">
                                            <h4 class="mb-0 text-center">{{ translate('Image') }} <span class="text-danger">*</span> </h4>                                                
                                        </div>
                                        <div class="text-center">
                                            <div class="upload-file_custom ratio-1 h-150px mx-auto">
                                                <input type="file" name="image"
                                                        class="upload-file__input single_file_input"
                                                        accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                        data-maxFileSize="{{ readableUploadMaxFileSize('image') }}" required>
                                                <label class="upload-file__wrapper w-100 h-100 m-0">
                                                    <div class="upload-file-textbox text-center" style="">
                                                        <img class="svg" src="{{asset('public/assets/admin/img/document-upload.svg')}}" alt="img">
                                                        <h6
                                                            class="mt-1 tc-clr fw-medium fs-10 lh-base text-center">
                                                            <span class="text-c2">Click to upload</span>
                                                            <br>
                                                            Or drag and drop
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
                                            <span class="font-medium text-title">(1:1)</span>
                                        </p>
                                    </div>
                                    <!-- <div class="form-group text-center">
                                        <label class="input-label font-weight-semibold mb-0 d-block">{{ translate('Image') }} <span class="text-danger">*</span></label>

                                        <div class="upload-file inline-size-180 mx-auto cmn_focus rounded mt-2">
                                            <input type="file" name="image" class="upload-file__input" tabindex="5"
                                                    accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                    data-maxFileSize="{{ readableUploadMaxFileSize('image') }}" required>
                                            <label class="upload-file-wrapper d-flex justify-content-center align-items-center m-auto">
                                                <div class="upload-file-textbox text-center">
                                                    <img width="34" height="34" src="{{ asset('public/assets/admin/img/document-upload.svg') }}" alt="">
                                                    <h6 class="mt-2 font-weight-semibold text-center">
                                                        <span class="text-c2">{{ translate('Click to upload') }}</span><br>{{ translate('or drag and drop') }}
                                                    </h6>
                                                </div>
                                                <img class="upload-file-img" loading="lazy" style="display: none;" alt="">
                                            </label>
                                        </div>
                                        <div class="mt-2 text-center">
                                            <p class="text-muted mb-0">{{ translate('Image format')}} - {{ implode(', ', array_column(IMAGEEXTENSION, 'key')) }}</p>
                                            <p class="text-muted mb-0">{{ translate("Image Size") }} - {{ translate('maximum size') }} {{ readableUploadMaxFileSize('image') }}</p>
                                            <p class="text-muted mb-0">{{ translate('Image Ratio') }} - 1:1</p>
                                        </div>
                                    </div> -->
                                </div>
                            </div>

                        </div>

                        <div class="d-flex justify-content-end gap-3 mt-20">
                            <button type="reset" class="btn btn-secondary">{{translate('Reset')}}</button>
                            <button type="submit" class="btn btn-primary">{{translate('submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
    <script src="{{asset('public/assets/admin')}}/js/select2.min.js"></script>
    <script>
        $(".js-example-theme-single").select2({
            theme: "classic"
        });

        $(".js-example-responsive").select2({
            width: 'resolve'
        });
    </script>
@endpush

@push('script_2')
    <script>
        $(document).ready(function() {
            $('.upload-file__input').on('change', function(event) {
                var file = event.target.files[0];
                var $card = $(event.target).closest('.upload-file');
                var $textbox = $card.find('.upload-file-textbox');
                var $imgElement = $card.find('.upload-file-img');

                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $textbox.hide();
                        $imgElement.attr('src', e.target.result).show();
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
@endpush
