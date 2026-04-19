@extends('layouts.admin.app')

@section('title', translate('Add new notification'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <i class="tio-notifications"></i>
                <span class="page-header-title">
                    {{translate('send_Notification')}}
                </span>
            </h2>
        </div>

        <div class="row g-2">
            <div class="col-12">
                <form action="{{route('admin.notification.store')}}" method="post" enctype="multipart/form-data">
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
                                            </label>
                                            <input type="text" name="title" maxlength="100" class="form-control" value="{{ old('title') }}" placeholder="{{translate('New notification')}}" tabindex="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="input-label">{{translate('description')}}
                                                <i class="tio-info text-danger" data-toggle="tooltip" data-placement="right"
                                                   title="{{ translate('not_more_than_255_characters') }}">
                                                </i>
                                            </label>
                                            <textarea name="description" maxlength="255" class="form-control" rows="3" placeholder="{{translate('Description...')}}" tabindex="2" required>{{ old('description') }}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class=" card p-4 h-100">
                                        <div class="pt-4">
                                            <div class="mb-4">
                                                <h4 class="mb-0 text-center">{{ translate('notification_Banner') }} <span class="text-danger">*</span> </h4>
                                            </div>
                                            <div class="text-center">
                                                <div class="upload-file_custom ratio-3-1 h-100px mx-auto">
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
                                                <span class="font-medium text-title">(3:1)</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <button type="reset" id="reset" class="btn btn-secondary" tabindex="4">{{translate('reset')}}</button>
                                <button type="submit" class="btn btn-primary" tabindex="5">{{translate('send_notification')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-12">
                <div class="card mt-3">
                    <div class="card-top px-card pt-4">
                        <div class="row justify-content-between align-items-center gy-2">
                            <div class="col-sm-4 col-md-6 col-lg-8">
                                <h5 class="d-flex align-items-center gap-2 mb-0">
                                    {{translate('Notification_Table')}}
                                    <span class="badge badge-soft-dark rounded-50 fz-12">{{ $notifications->total() }}</span>
                                </h5>
                            </div>
                            <div class="col-sm-8 col-md-6 col-lg-4">
                                <form action="{{url()->current()}}" method="GET">
                                    <div class="input-group">
                                        <input id="datatableSearch_" type="search" name="search" class="form-control" placeholder="{{translate('Search by title or description')}}" aria-label="Search" value="{{$search}}" required="" autocomplete="off" tabindex="6">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary" tabindex="7">
                                                {{translate('Search')}}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>


                    <div class="py-3">
                        <div class="table-responsive datatable-custom">
                            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                                <thead class="thead-light">
                                <tr>
                                    <th>{{translate('SL')}}</th>
                                    <th>{{translate('image')}}</th>
                                    <th>{{translate('title')}}</th>
                                    <th>{{translate('description')}}</th>
                                    <th>{{translate('status')}}</th>
                                    <th class="text-center">{{translate('action')}}</th>
                                </tr>
                                </thead>

                                <tbody>
                                @foreach($notifications as $key=>$notification)
                                    <tr>
                                        <td>{{$notifications->firstitem()+$key}}</td>
                                        <td>
                                            @if($notification['image']!=null)
                                                <img class="img-vertical-150 min-w-120px" src="{{$notification['imageFullPath']}}" alt="{{ translate('notification') }}">
                                            @else
                                                <label class="badge badge-soft-warning">{{translate('No')}} {{translate('image')}}</label>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="max-w300 min-w-120px text-wrap">
                                                {{substr($notification['title'],0,25)}} {{strlen($notification['title'])>25?'...':''}}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="max-w300 min-w-120px text-wrap">
                                                {{substr($notification['description'],0,25)}} {{strlen($notification['description'])>25?'...':''}}
                                            </div>
                                        </td>
                                        <td>
                                            <label class="switcher">
                                                <input class="switcher_input status-change" type="checkbox" id="{{$notification['id']}}"
                                                    data-url="{{route('admin.notification.status',[$notification['id'],0])}}" {{$notification['status'] == 1? 'checked' : ''}}>
                                                <span class="switcher_control"></span>
                                            </label>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a class="btn btn-outline-info btn-sm edit square-btn"
                                                href="{{route('admin.notification.edit',[$notification['id']])}}"><i class="tio-edit"></i></a>
                                                <button type="button" class="btn btn-outline-danger btn-sm delete square-btn notification-delete-btn" data-id="{{$notification['id']}}">
                                                    <i class="tio-delete"></i>
                                                </button>
                                            </div>
                                            <form
                                                action="{{route('admin.notification.delete',[$notification['id']])}}"
                                                method="post" id="notification-{{$notification['id']}}">
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
                                {!! $notifications->links() !!}
                            </div>
                        </div>

                        @if(count($notifications) == 0)
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
    <script src="{{asset('public/assets/admin/js/read-url.js')}}"></script>

    <script>
        "use strict";

        $('.notification-delete-btn').click(function() {
            var notificationId = $(this).data('id');
            $('#notification-' + notificationId).submit();
        });
    </script>
@endpush
