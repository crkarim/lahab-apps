@extends('layouts.admin.app')

@section('title', $category->parent_id == 0 ? translate('Update Category') : translate('Update Sub Category')))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/category.png')}}" alt="">
                <span class="page-header-title">
                    {{ $category->parent_id == 0 ? translate('Update Category') : translate('Update Sub Category') }}
                </span>
            </h2>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card card-body">
                    <form action="{{route('admin.category.update',[$category['id']])}}" method="post" enctype="multipart/form-data">
                        @csrf

                        @php($data = Helpers::get_business_settings('language'))
                        @php($defaultLang = Helpers::get_default_language())

                        @if($data && array_key_exists('code', $data[0]))
                            <ul class="nav nav-tabs w-fit-content mb-4">
                                @foreach($data as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link {{$lang['default'] == true? 'active':''}}" href="#"
                                        id="{{$lang['code']}}-link">{{\App\CentralLogics\Helpers::get_language_name($lang['code']).'('.strtoupper($lang['code']).')'}}</a>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="row align-items-end g-3">
                                <div class="col-12">
                                    @foreach($data as $lang)
                                        <?php
                                        if (count($category['translations'])) {
                                            $translate = [];
                                            foreach ($category['translations'] as $t) {
                                                if ($t->locale == $lang['code'] && $t->key == "name") {
                                                    $translate[$lang['code']]['name'] = $t->value;
                                                }
                                            }
                                        }
                                        ?>
                                        <div class="form-group mb-0 {{$lang['default'] == false ? 'd-none':''}} lang_form"
                                            id="{{$lang['code']}}-form">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{translate('name')}}
                                                ({{strtoupper($lang['code'])}})</label>
                                            <input type="text" name="name[]" maxlength="255"
                                                value="{{$lang['code'] == 'en' ? $category['name'] : ($translate[$lang['code']]['name']??'')}}"
                                                class="form-control" @if($lang['status'] == true) oninvalid="document.getElementById('{{$lang['code']}}-link').click()" @endif
                                                placeholder="{{ translate('New Category') }}" {{$lang['status'] == true ? 'required':''}}>
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{$lang['code']}}">
                                    @endforeach
                                    @else
                                    <div class="row">
                                        <div class="col-12 mb-4">
                                            <div class="form-group lang_form" id="{{$defaultLang}}-form">
                                                <label class="input-label"
                                                    for="exampleFormControlInput1">{{translate('name')}}
                                                    ({{strtoupper($defaultLang)}})</label>
                                                <input type="text" name="name[]" value="{{$category['name']}}"
                                                    class="form-control" oninvalid="document.getElementById('en-link').click()"
                                                    placeholder="{{ translate('New Category') }}" required>
                                            </div>
                                            <input type="hidden" name="lang[]" value="{{$defaultLang}}">
                                            @endif
                                            <input class="position-area" name="position" value="0">
                                        </div>

                                        <div class="col-lg-4">
                                            <div class="bg-soft-secondary rounded p-20 h-100">
                                                <div class="mb-4">
                                                    <h4 class="mb-0">{{ translate('Image') }} <span class="text-danger">*</span> </h4>
                                                </div>
                                                <div class="text-center">
                                                    <div class="upload-file_custom ratio-1 h-100px mx-auto">
                                                        <input type="file" name="image"
                                                                class="upload-file__input single_file_input"
                                                                accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                            data-maxFileSize="{{ readableUploadMaxFileSize('image') }}">
                                                        <label class="upload-file__wrapper w-100 h-100 m-0">
                                                            <div class="upload-file-textbox text-center" style="">
                                                                <img class="svg" src="{{asset('public/assets/admin/img/document-upload.svg')}}" alt="img">
                                                                <h6
                                                                    class="mt-1 tc-clr fw-medium fs-10 lh-base text-center">
                                                                    <span class="text-c2">{{ translate('Click to upload') }}</span>
                                                                    <br>
                                                                    {{ translate('Or drag and drop') }}
                                                                </h6>
                                                            </div>
                                                            <img class="upload-file-img" loading="lazy" src="{{$category->imageFullPath}}"
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
                                        <div class="col-lg-8">
                                            <div class="bg-soft-secondary rounded p-20 h-100">
                                                <div class="mb-4">
                                                    <h4 class="mb-0">{{ translate('Banner Image') }} <span class="text-danger">*</span> </h4>
                                                </div>
                                                <div class="text-center">
                                                    <div class="upload-file_custom ratio-8-1 h-100px mx-auto">
                                                        <input type="file" name="banner_image"
                                                                class="upload-file__input single_file_input"
                                                            accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                            data-maxFileSize="{{ readableUploadMaxFileSize('image') }}">
                                                        <label class="upload-file__wrapper w-100 h-100 m-0">
                                                            <div class="upload-file-textbox text-center" style="">
                                                                <img class="svg" src="{{asset('public/assets/admin/img/document-upload.svg')}}" alt="img">
                                                                <h6
                                                                    class="mt-1 tc-clr fw-medium fs-10 lh-base text-center">
                                                                    <span class="text-c2">{{ translate('Click to upload') }}</span>
                                                                    <br>
                                                                    {{ translate('Or drag and drop') }}
                                                                </h6>
                                                            </div>
                                                            <img class="upload-file-img" loading="lazy" src="{{$category->bannerImageFullPath}}"
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
                                                    <span class="font-medium text-title">(8:1)</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-3 mt-4">
                                        <button type="reset" class="btn btn-secondary">{{translate('reset')}}</button>
                                        <button type="submit" class="btn btn-primary">{{translate('update')}}</button>
                                    </div>

                    </form>

                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        function readURL(input, viewerId) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $('#' + viewerId).attr('src', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function () {
            readURL(this, 'viewer');
        });

        $("#customFileEg2").change(function () {
            readURL(this, 'viewer2');
        });

        $(".lang_link").click(function(e){
            e.preventDefault();

            $(".lang_link").removeClass('active');
            $(".lang_form").addClass('d-none');
            $(this).addClass('active');

            let form_id = this.id;
            let lang = form_id.split("-")[0];

            $("#"+lang+"-form").removeClass('d-none');

            if(lang == '{{$defaultLang}}')
            {
                $(".from_part_2").removeClass('d-none');
            }
            else
            {
                $(".from_part_2").addClass('d-none');
            }
        });
    </script>
@endpush
