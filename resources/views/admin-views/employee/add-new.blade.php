@extends('layouts.admin.app')

@section('title', translate('Employee Add'))

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
        <h2 class="h1 mb-0 d-flex align-items-center gap-2">
            <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/employee.png')}}" alt="">
            <span class="page-header-title">
                {{translate('add_New_Employee')}}
            </span>
        </h2>
    </div>

    <div class="row">
        <div class="col-md-12">
            <form action="{{route('admin.employee.add-new')}}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0 d-flex align-items-center gap-2"><span class="tio-user"></span> {{translate('general_Information')}}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">{{translate('full_Name')}}</label>
                                    <input type="text" name="name" class="form-control" id="name"
                                        placeholder="{{translate('Ex')}} : {{translate('Jhon_Doe')}}" value="{{old('name')}}" tabindex="1" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">{{translate('Phone')}}</label>
                                    <input type="tel" name="phone" value="{{old('phone')}}" class="form-control" id="phone"
                                        placeholder="{{translate('Ex')}} : +88017********" tabindex="2" required>
                                </div>

                                <div class="form-group">
                                    <label for="role_id">{{translate('Role')}}</label>
                                    <select class="custom-select" tabindex="3" name="role_id">
                                        <option value="0" selected disabled>---{{translate('select_Role')}}---</option>
                                        @foreach($roles as $role)
                                            <option value="{{$role->id}}" {{old('role_id')==$role->id?'selected':''}}>{{$role->name}}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="identity_type">{{translate('Identity Type')}}</label>
                                    <select class="custom-select" name="identity_type" id="identity_type" tabindex="4" required>
                                        <option selected disabled>---{{translate('select_Identity_Type')}}---</option>
                                        <option value="passport">{{translate('passport')}}</option>
                                        <option value="driving_license">{{translate('driving_License')}}</option>
                                        <option value="nid">{{translate('NID')}}</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="identity_number">{{translate('identity_Number')}}</label>
                                    <input type="text" name="identity_number" class="form-control" id="identity_number" required value="{{old('identity_number')}}" tabindex="5">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card py-4 px-2">
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
                                        <span class="font-medium text-title">(1:1)</span>
                                    </p>
                                </div>
                                <div class="form-group mb-0 mt-4">
                                    <div class="mb-15">
                                        <label class="input-label">{{translate('identity_Image')}}</label>
                                        <p class="text-muted mb-0">
                                            {{ translate('Image format')}} - {{ implode(', ', array_column(IMAGEEXTENSION, 'key')) }}
                                            {{ translate("Image Size") }} - {{ translate('maximum size') }} {{ readableUploadMaxFileSize('image') }}
                                            {{ translate('Image Ratio') }} - 1:1
                                        </p>
                                    </div>
                                    <div>
                                        <div class="row g-2" id="coba"></div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0 d-flex align-items-center gap-2"><span class="tio-user"></span> {{translate('account_Information')}}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="email">{{translate('Email')}}</label>
                                    <input type="email" name="email" value="{{old('email')}}" class="form-control" id="email"
                                        placeholder="{{translate('Ex')}} : ex@gmail.com" tabindex="7" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="password">{{translate('password')}}</label>
                                    <div class="input-group input-group-merge">
                                        <input type="password" name="password" class="js-toggle-password form-control form-control input-field" id="password"
                                               placeholder="{{translate('Ex: 8+ Characters')}}" required
                                               data-hs-toggle-password-options='{
                                        "target": "#changePassTarget",
                                        "defaultClass": "tio-hidden-outlined",
                                        "showClass": "tio-visible-outlined",
                                        "classChangeTarget": "#changePassIcon"
                                        }' tabindex="8">
                                        <div id="changePassTarget" class="input-group-append">
                                            <a class="input-group-text" href="javascript:">
                                                <i id="changePassIcon" class="tio-visible-outlined"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="confirm_password">{{translate('confirm_Password')}}</label>
                                    <div class="input-group input-group-merge">
                                        <input type="password" name="confirm_password" class="js-toggle-password form-control form-control input-field"
                                               id="confirm_password" placeholder="{{translate('confirm password')}}" required
                                               data-hs-toggle-password-options='{
                                                "target": "#changeConPassTarget",
                                                "defaultClass": "tio-hidden-outlined",
                                                "showClass": "tio-visible-outlined",
                                                "classChangeTarget": "#changeConPassIcon"
                                                }' tabindex="9">
                                        <div id="changeConPassTarget" class="input-group-append">
                                            <a class="input-group-text" href="javascript:">
                                                <i id="changeConPassIcon" class="tio-visible-outlined"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3">
                    <button type="reset" id="reset" class="btn btn-secondary" tabindex="10">{{translate('reset')}}</button>
                    <button type="submit" class="btn btn-primary" tabindex="11">{{translate('submit')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/vendor.min.js')}}"></script>
    <script src="{{asset('public/assets/admin')}}/js/select2.min.js"></script>
    <script src="{{asset('public/assets/admin/js/image-upload.js')}}"></script>
    <script src="{{ asset('public/assets/admin/js/read-url.js') }}"></script>
    <script src="{{asset('public/assets/admin/js/spartan-multi-image-picker.js')}}"></script>
    <script>
        "use strict";

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


        $(".js-example-theme-single").select2({
            theme: "classic"
        });

        $(".js-example-responsive").select2({
            width: 'resolve'
        });

        $(function () {

            let maxSizeReadable = "{{ readableUploadMaxFileSize('image') }}";
            let maxFileSize = 2 * 1024 * 1024; // default 2MB

            if (maxSizeReadable.toLowerCase().includes('mb')) {
                maxFileSize = parseFloat(maxSizeReadable) * 1024 * 1024;
            } else if (maxSizeReadable.toLowerCase().includes('kb')) {
                maxFileSize = parseFloat(maxSizeReadable) * 1024;
            }

            $("#coba").spartanMultiImagePicker({
                fieldName: 'identity_image[]',
                maxCount: 5,
                rowHeight: '230px',
                groupClassName: 'col-6 col-lg-4 col-xxl-3',
                maxFileSize: maxFileSize,
                placeholderImage: {
                    image: '{{asset('public/assets/admin/img/400x400/img2.jpg')}}',
                    width: '100%'
                },
                dropFileLabel: "Drop Here",
                onAddRow: function (index, file) {

                },
                onRenderedPreview: function (index) {

                },
                onRemoveRow: function (index) {

                },
                onExtensionErr: function (index, file) {
                    toastr.error('{{translate("Please only input png or jpg type file")}}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                },
                onSizeErr: function (index, file) {
                    toastr.error('File size must be less than ' + maxSizeReadable, {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        });
    </script>
@endpush
