@extends('layouts.admin.app')

@section('title', translate('Promotional Campaign'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/campaign.png')}}" alt="">
                <span class="page-header-title">
                    {{translate('Promotion_Setup')}}
                </span>
            </h2>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.promotion.store')}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{translate('Select Branch')}} <span class="text-danger ml-1">*</span></label>
                                <select name="branch_id" class="custom-select" required>
                                    <option disabled selected>{{ translate('--select--') }}</option>
                                    @foreach($branches as $branch)
                                        <option value="{{$branch['id']}}">{{$branch['name']}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label">{{translate('Select Banner Type')}} <span class="text-danger ml-1">*</span></label>
                                <select name="banner_type" id="banner_type" class="custom-select" required>
                                    <option value="" selected>{{ translate('--select--') }}</option>
                                    <option value="bottom_banner">{{ translate('Bottom Banner (1110*380 px)') }}</option>
                                    <option value="top_right_banner">{{ translate('Top Right Banner (280*450 px)') }}</option>
                                    <option value="bottom_right_banner">{{ translate('Bottom Right Banner (280*350 px)') }}</option>
                                    <option value="video">{{ translate('Video') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class=" from_part_2 video_section d--none" id="video_section">
                                    <label class="input-label">{{translate('youtube Video URL')}}<span class="text-danger ml-1">*</span></label>
                                    <input type="text" name="video" class="form-control" placeholder="{{ translate('ex : https://youtu.be/0sus46BflpU') }}">
                                </div>
                                <div class=" from_part_2 image_section d--none" id="image_section">
                                    <div class="border rounded p-4">
                                        <div class="mb-4">
                                            <h4 class="mb-0">{{ translate('Image') }} <span class="text-danger">*</span> </h4>
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
                    </div>
                    <div class="d-flex justify-content-end gap-3">
                        <button type="reset" class="btn btn-secondary">{{translate('reset')}}</button>
                        <button type="submit" class="btn btn-primary">{{translate('Save')}}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-top px-card pt-4">
                <div class="row justify-content-between align-items-center gy-2">
                    <div class="col-sm-4 col-md-6 col-lg-8">
                        <h5 class="d-flex gap-2 mb-0">
                            {{translate('Promotion_Table')}}
                            <span class="badge badge-soft-dark rounded-50 fz-12">{{$promotions->total()}}</span>
                        </h5>
                    </div>
                    <div class="col-sm-8 col-md-6 col-lg-4">
                        <form action="{{url()->current()}}" method="GET">
                            <div class="input-group">
                                <input id="datatableSearch_" type="search" name="search" class="form-control" placeholder="{{translate('Search by Type')}}" aria-label="Search" value="{{$search}}" required="" autocomplete="off">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">{{translate('Search')}}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="py-4">
                <div class="table-responsive">
                    <table id="datatable" class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>{{translate('SL')}}</th>
                            <th>{{translate('Branch')}}</th>
                            <th>{{translate('Promotion type')}}</th>
                            <th>{{translate('Promotion_Banner')}}</th>
                            <th class="text-center">{{translate('action')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($promotions as $k=>$promotion)
                            <tr>
                                <th scope="row" class="align-middle">{{$promotions->firstitem() + $k}}</th>
                                <td>
                                    <a class="text-dark" href="{{route('admin.promotion.branch',[$promotion->branch_id])}}">{{$promotion->branch->name}}</a>
                                </td>
                                <td>
                                    @php
                                        $promotionType = $promotion['promotion_type'];
                                        echo str_replace('_', ' ', $promotionType);
                                    @endphp
                                </td>
                                <td>
                                    @if($promotion['promotion_type'] == 'video')
                                        {{$promotion['promotion_name']}}
                                    @else
                                        <div>
                                            <img class="mx-80px" width="100" src="{{$promotion->promotionNameFullPath}}">
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center gap-3">
                                        <a href="{{route('admin.promotion.edit',[$promotion['id']])}}"
                                        class="btn btn-outline-info btn-sm square-btn"
                                        title="{{translate('Edit')}}">
                                            <i class="tio-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm square-btn form-alert" title="{{translate('Delete')}}"
                                        data-id="promotion-{{$promotion['id']}}" data-message="{{translate('Want to delete this promotion ?')}}">
                                            <i class="tio-delete"></i>
                                        </button>
                                    </div>
                                    <form action="{{route('admin.promotion.delete',[$promotion['id']])}}"
                                            method="post" id="promotion-{{$promotion['id']}}">
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
                        {{$promotions->links()}}
                    </div>
                </div>

                @if(count($promotions) == 0)
                    <div class="text-center p-4">
                        <img class="w-120px mb-3" src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="Image Description">
                        <p class="mb-0">{{translate('No_data_to_show')}}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/image-upload.js')}}"></script>
    <script>
        "use strict";

        $(function() {
            $('#banner_type').change(function(){
                if ($(this).val() === 'video'){
                    $('#video_section').show();
                    $('#image_section').hide();
                }else{
                    $('#video_section').hide();
                    $('#image_section').show();
                }
            });
        });
    </script>
@endpush
