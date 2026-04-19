@extends('layouts.admin.app')

@section('title', translate('Promotional Campaign'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/campaign.png')}}" alt="">
                <span class="page-header-title">
                    {{translate('promotion_update')}}
                </span>
            </h2>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.promotion.update',[$promotion['id']])}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{translate('Select Branch')}}<span class="text-danger">*</span>
                                </label>
                                <select name="branch_id" class="form-control js-select2-custom" required>
                                    <option value="" selected>{{ translate('--select--') }}</option>
                                    @foreach($branches as $branch)
                                        <option value="{{$branch['id']}}" {{ $promotion->branch_id == $branch->id ? 'selected' : '' }}>{{$branch['name']}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{translate('Select Banner Type')}}<span class="text-danger">*</span></label>
                                <select name="banner_type" id="banner_type" class="form-control" required>
                                    <option value="" selected>{{ translate('--select--') }}</option>
                                    <option value="bottom_banner" {{ $promotion->promotion_type == 'bottom_banner' ? 'selected' : '' }}>{{ translate('Bottom Banner (1110*380 px)') }}</option>
                                    <option value="top_right_banner" {{ $promotion->promotion_type == 'top_right_banner' ? 'selected' : '' }}>{{ translate('Top Right Banner (280*450 px)') }}</option>
                                    <option value="bottom_right_banner" {{ $promotion->promotion_type == 'bottom_right_banner' ? 'selected' : '' }}>{{ translate('Bottom Right Banner (280*350 px)') }}</option>
                                    <option value="video" {{ $promotion->promotion_type == 'video' ? 'selected' : '' }}>{{ translate('Video') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">

                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="from_part_2 video_section" id="video_section">
                                    <label class="input-label" for="exampleFormControlSelect1">{{translate('youtube Video URL')}} <span class="text-danger">*</span></label>
                                    <input type="text" name="video" value="{{$promotion->promotion_name}}" class="form-control" placeholder="{{ translate('ex : https://youtu.be/0sus46BflpU') }}">
                                </div>
                                <div class="from_part_2 image_section" id="image_section">
                                    <div class="border rounded p-4">
                                        <div class="mb-4">
                                            <h4 class="mb-0">{{ translate('Image') }} <span class="text-danger">*</span> </h4>
                                        </div>
                                            <div class="text-center">
                                                <div class="upload-file_custom ratio-1 h-150px mx-auto">
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
                                                        <img class="upload-file-img" loading="lazy" src="{{$promotion->promotionNameFullPath}}"
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
                                    <!-- <label class="input-label">{{translate('Image')}}<span class="text-danger">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" name="image" id="customFileUpload" class="custom-file-input"
                                               accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*"
                                               oninvalid="document.getElementById('en-link').click()">
                                        <label class="custom-file-label" for="customFileUpload">{{ translate('choose file') }}</label>
                                    </div>
                                    <div class="from_part_2 mt-3">
                                        <div class="form-group">
                                            <div class="text-center">
                                                <img width="180" class="rounded-10 border maxh-170px" id="viewer"
                                                     src="{{$promotion->promotionNameFullPath}}">
                                            </div>
                                        </div>
                                    </div> -->
                                </div>
{{--                                @endif--}}
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3">
                        <button type="reset" class="btn btn-secondary">{{translate('reset')}}</button>
                        <button type="submit" class="btn btn-primary">{{translate('Update')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/image-upload.js')}}"></script>
    <script>
        "use strict";

        $(document).ready(function() {
            function toggleSections(type) {
                if (type === 'video') {
                    $('#video_section').show();
                    $('#image_section').hide();
                } else {
                    $('#video_section').hide();
                    $('#image_section').show();
                    $('input[name="video"]').val('');
                }
            }

            toggleSections($('#banner_type').val());

            $('#banner_type').change(function() {
                toggleSections($(this).val());
            });
        });

    </script>
@endpush
