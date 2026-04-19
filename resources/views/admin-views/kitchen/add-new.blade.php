@extends('layouts.admin.app')

@section('title', translate('Add New Chef'))

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

    <div class="row g-3">
        <div class="col-md-12">
            <div class="">
                <div class="card-body p-0">
                    <form action="{{route('admin.kitchen.add-new')}}" method="post" enctype="multipart/form-data">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="card h-100 p-4">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="input-label" for="exampleFormControlSelect1">{{translate('Select Branch')}} <span class="text-danger">*</span></label>
                                                <select name="branch_id" class="custom-select" required>
                                                    <option value="" selected disabled>{{ translate('--Select_Branch--') }}</option>
                                                    @foreach($branches as $branch)
                                                        <option value="{{$branch['id']}}">{{$branch['name']}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

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
                                        <div class="col-md-6 mb-3">
                                            <label for="name">{{translate('Phone')}} <span class="text-danger">*</span></label>
                                            <input type="tel" name="phone" value="{{old('phone')}}" class="form-control" id="phone"
                                                   placeholder="{{translate('Ex')}} : +88017********" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="name">{{translate('Email')}} <span class="text-danger">*</span></label>
                                            <input type="email" name="email" value="{{old('email')}}" class="form-control" id="email"
                                                   placeholder="{{translate('Ex')}} : ex@gmail.com" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name">{{translate('password')}} <span class="text-danger">*</span> {{translate('(minimum length will be 6 character)')}}</label>
                                            <div class="input-group input-group-merge">
                                                <input type="password" name="password" class="js-toggle-password form-control form-control input-field" id="password"
                                                       placeholder="{{translate('Password')}}" required
                                                       data-hs-toggle-password-options='{
                                            "target": "#changePassTarget",
                                            "defaultClass": "tio-hidden-outlined",
                                            "showClass": "tio-visible-outlined",
                                            "classChangeTarget": "#changePassIcon"
                                            }'>
                                                <div id="changePassTarget" class="input-group-append">
                                                    <a class="input-group-text" href="javascript:">
                                                        <i id="changePassIcon" class="tio-visible-outlined"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password">{{translate('confirm_Password')}}<span class="text-danger">*</span></label>
                                            <div class="input-group input-group-merge">
                                                <input type="password" name="confirm_password" class="js-toggle-password form-control form-control input-field" id="confirm_password"
                                                       placeholder="{{translate('confirm password')}}" required
                                                       data-hs-toggle-password-options='{
                                            "target": "#changeConPassTarget",
                                            "defaultClass": "tio-hidden-outlined",
                                            "showClass": "tio-visible-outlined",
                                            "classChangeTarget": "#changeConPassIcon"
                                            }'>
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
                            <div class="col-md-4">
                                <div class="card d-center h-100 p-4">
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
                                </div>

                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-3 mt-4">
                            <button type="reset" id="reset" class="btn btn-secondary">{{translate('reset')}}</button>
                            <button type="submit" class="btn btn-primary">{{translate('Submit')}}</button>
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
