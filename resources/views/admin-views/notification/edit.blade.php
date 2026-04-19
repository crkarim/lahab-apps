@extends('layouts.admin.app')

@section('title', translate('Update Notification'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <i class="tio-notifications"></i>
                <span class="page-header-title">
                    {{translate('notification_Update')}}
                </span>
            </h2>
        </div>

        <div class="row g-2">
            <div class="col-12">
                <form action="{{route('admin.notification.update',[$notification['id']])}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="">
                        <div class="card-body p-0">
                            <div class="row g-2">
                                <div class="col-lg-8">
                                    <div class="card p-4">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('title')}}
                                                <i class="tio-info text-danger" data-toggle="tooltip" data-placement="right"
                                                   title="{{ translate('not_more_than_100_characters') }}">
                                                </i>
                                            </label>                                        <input type="text" value="{{$notification['title']}}" name="title" class="form-control" placeholder="{{translate('New notification')}}" tabindex="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="input-label">{{translate('description')}}
                                                <i class="tio-info text-danger" data-toggle="tooltip" data-placement="right"
                                                   title="{{ translate('not_more_than_255_characters') }}">
                                                </i>
                                            </label>
                                            <textarea name="description" class="form-control" rows="3" tabindex="2" required>{{$notification['description']}}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class=" card p-4 h-100">
                                        <div class="">
                                            <div class="mb-4 pt-4">
                                                <h4 class="mb-0 text-center">{{ translate('update_Banner') }} </h4>
                                            </div>
                                            <div class="text-center">
                                                <div class="upload-file_custom ratio-3-1 h-100px mx-auto">
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
                                                        <img class="upload-file-img" loading="lazy" src="{{$notification['imageFullPath']}}"
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
                                                <span class="font-medium text-title">(3:1)</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <button type="reset" class="btn btn-secondary" tabindex="4">{{translate('reset')}}</button>
                                <button type="submit" class="btn btn-primary" tabindex="5">{{translate('submit')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/read-url.js')}}"></script>
@endpush
