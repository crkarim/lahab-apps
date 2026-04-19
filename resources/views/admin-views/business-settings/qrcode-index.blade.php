@extends('layouts.admin.app')

@section('title', translate('Business Settings'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/business_setup2.png')}}" alt="">
                <span class="page-header-title">
                    {{translate('business_setup')}}
                </span>
            </h2>
        </div>

        @include('admin-views.business-settings.partials._business-setup-inline-menu')

        <section class="qr-code-section">
            <div class="card">
                <div class="card-body">
                    <div class="qr-area">
                        <div class="left-side pr-xl-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="text-dark w-0 flex-grow-1">{{ translate('QR Card Design') }}</div>
                                <div class="btn--container flex-nowrap print-btn-grp">
                                    <a type="button" href="{{ route('admin.business-settings.restaurant.qrcode.print') }}" class="btn cmn_focus btn-primary pt-1" tabindex="1"><i class="tio-print"></i> {{translate('Print')}}</a>
                                </div>
                            </div>
                            @php($restaurantLogo=\App\Model\BusinessSetting::where(['key'=>'logo'])->first()?->value)
                            <div class="qr-wrapper" style="background: url({{asset('public/assets/admin/img/qr-bg.png')}}) no-repeat center center / 100% 100%">
                                <a href="javascript:" class="qr-logo ratio-3-1" style="max-width: 300px; min-width: 100px;">
                                    <img src="{{asset('storage/app/public/qrcode/'.$data['logo'])}}"
                                         onerror="this.src='{{asset('public/assets/admin/img/logo2.png')}}'"
                                         id="logoViewer" alt="" class="w-100 h-100 object-cover">

                                </a>
                                <p class="view-menu-title" id="qr-title">
                                    {{ isset($data) ? $data['title'] : translate('title') }}
                                </p>
                                <div class="text-center mt-4">
                                    <div>
                                        <img src="{{asset('public/assets/admin/img/scan-me.png')}}" class="mw-100" alt="">
                                    </div>
                                    <div class="my-3" >
                                        {!! $code !!}
                                    </div>
                                </div>
                                <div class="subtext" id="qr-description">
                                    <span>
                                        {{ isset($data) ? $data['description'] : translate('description') }}
                                    </span>
                                </div>
                                <div class="open-time">
                                    <div>{{ translate('OPEN DAILY') }}</div>
                                    <div><span id="qr-opening-time">{{ isset($data) ? $data['opening_time'] : '09:00 AM' }}</span> - <span id="qr-ending-time">{{ isset($data) ? $data['closing_time'] : '09:00 PM' }}</span></div>
                                </div>
                                <div class="phone-number">
                                    {{ translate('PHONE NUMBER') }} : <span id="qr-phone">{{ isset($data) ? $data['phone'] : '+00 123 4567890' }}</span>
                                </div>
                                <div class="row g-0 text-center bottom-txt">
                                    <div class="col-6 border-right py-3 px-2" id="qr-website">
                                        {{ isset($data) ? $data['website'] : 'www.website.com' }}
                                    </div>
                                    <div class="col-6 py-3" id="qr-social">
                                        {{ isset($data) ? $data['social_media'] : translate('@social-media-name') }}

                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="right-side">
                            <form method="post" action="{{ route('admin.business-settings.restaurant.qrcode.store') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="row">
                                    <div class="col-12" id="branch_section">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Branch')}}</label>
                                            <select class="form-control js-select2-custom" name="branch_id" tabindex="2">
                                                @foreach($branches as $branch)
                                                    <option value="{{ $branch['id'] }}" {{$branch['id'] == $data['branch_id'] ? "selected" : ""}}>{{ $branch['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="border rounded mb-20 p-4">
                                            <div class="mb-4">
                                                <h4 class="mb-0">{{ translate('Logo / Icon') }} <span class="text-danger">*</span> </h4>
                                            </div>
                                            <div class="text-center">
                                                <div class="upload-file_custom ratio-3-1 h-100px mx-auto">
                                                    <input type="file" name="logo"
                                                            class="upload-file__input single_file_input"
                                                            accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                            data-maxFileSize="{{ readableUploadMaxFileSize('image') }}">
                                                    <label class="upload-file__wrapper w-100 h-100 m-0">
                                                        <div class="upload-file-textbox text-center" style="">
                                                            <img class="svg"
                                                                 src="{{isset($data) && $data['logo'] ? asset('storage/app/public/qrcode/'.$data['logo']) : asset('public/assets/admin/img/document-upload.svg')}}" alt="img">
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
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Title')}}</label>
                                            <input type="text" name="title" placeholder="{{ translate('Ex : Title') }}" data-id="qr-title"
                                                   class="form-control" value="{{isset($data) ? $data['title'] : old('title')}}" required tabindex="4">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Description')}}</label>
                                            <input type="text" name="description" placeholder="{{ translate('Ex : Description') }}" data-id="qr-description"
                                                   value="{{isset($data) ? $data['description'] : old('description')}}" class="form-control" required tabindex="5">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Opening Time')}}</label>
                                            <input type="time" class="form-control" name="opening_time" data-id="qr-opening-time"
                                                   value="{{isset($data) ? $data['opening_time'] : old('opening_time')}}" required tabindex="6">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Closing Time')}}</label>
                                            <input type="time" class="form-control" name="closing_time" data-id="qr-ending-time"
                                                   value="{{isset($data) ? $data['closing_time'] : old('closing_time')}}" required tabindex="7">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Phone')}}</label>
                                            <input type="text" name="phone" placeholder="{{ translate('Ex : +123456') }}" data-id="qr-phone"
                                                   value="{{isset($data) ? $data['phone'] : old('phone')}}" class="form-control" required tabindex="8">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Website Link')}}</label>
                                            <input type="text" name="website" value="{{isset($data) ? preg_replace('#/qr-category-screen/?$#', '', $data['website']) : old('website')}}"
                                                   data-id="qr-website" placeholder="{{ translate('Ex : www.website.com') }}" class="form-control" required tabindex="9">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('Social Media Name')}}</label>
                                            <input type="text" placeholder="{{ translate('@social media name')  }}" name="social_media" data-id="qr-social"
                                                   value="{{isset($data) ? $data['social_media'] : old('social_media')}}" class="form-control" required tabindex="10">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="btn--container">
                                            <button type="reset" class="btn btn-secondary" tabindex="11">{{translate('reset')}}</button>
                                            <button type="submit" class="btn btn-primary" tabindex="12">{{translate('submit')}}</button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('script_2')
    <script>

        // var editor = CKEDITOR.replace('ckeditor');
        //
        // editor.on( 'change', function( evt ) {
        //     $('#mail-body').empty().html(evt.editor.getData());
        // });

        $('input[data-id="qr-title"]').on('keyup', function() {
            var dataId = $(this).data('id');
            var value = $(this).val();
            console.log(value)
            $('#'+dataId).text(value);
        });
        $('input[data-id="qr-description"]').on('keyup', function() {
            var dataId = $(this).data('id');
            var value = $(this).val();
            $('#'+dataId).text(value);
        });
        $('input[data-id="qr-opening-time"]').on('change', function() {
            var dataId = $(this).data('id');
            var value = $(this).val();
            $('#'+dataId).text(value);
        });
        $('input[data-id="qr-ending-time"]').on('change', function() {
            var dataId = $(this).data('id');
            var value = $(this).val();
            $('#'+dataId).text(value);
        });
        $('input[data-id="qr-phone"]').on('keyup', function() {
            var dataId = $(this).data('id');
            var value = $(this).val();
            $('#'+dataId).text(value);
        });
        $('input[data-id="qr-website"]').on('keyup', function() {
            var dataId = $(this).data('id');
            var value = $(this).val();
            var fullValue = value + '/qr-category-screen';
            $('#' + dataId).text(fullValue);
        });
        $('input[data-id="qr-social"]').on('keyup', function() {
            var dataId = $(this).data('id');
            var value = $(this).val();
            $('#'+dataId).text(value);
        });

        function readURL(input, viewer) {

            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#' + viewer).attr('src', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#qrLogo").change(function() {
            readURL(this, 'logoViewer');
        });
    </script>
@endpush
