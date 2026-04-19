@extends('layouts.admin.app')

@section('title', translate('Add new category'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/category.png')}}" alt="">
                <span class="page-header-title">
                    {{translate('add_New_Category')}}
                </span>
            </h2>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <div class="card card-body">
                    <form action="{{route('admin.category.store')}}" method="post" enctype="multipart/form-data">
                        @csrf
                        @php($data = Helpers::get_business_settings('language'))
                        @php($defaultLang = Helpers::get_default_language())

                        @if ($data && array_key_exists('code', $data[0]))
                        <ul class="nav w-fit-content nav-tabs mb-4">
                            @foreach ($data as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link {{ $lang['default'] == true ? 'active' : '' }}" href="#"
                                    id="{{ $lang['code'] }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang['code']) . '(' . strtoupper($lang['code']) . ')' }}</a>
                                </li>
                            @endforeach
                        </ul>

                        <div class="row align-items-end g-3">
                            <div class="col-12">
                                @foreach ($data as $lang)
                                    <div class="form-group mb-0 {{ $lang['default'] == false ? 'd-none' : '' }} lang_form"
                                        id="{{ $lang['code'] }}-form">
                                        <label class="input-label" >{{ translate('name') }} ({{ strtoupper($lang['code']) }})</label>
                                        <input type="text" name="name[]" class="form-control" placeholder="{{ translate('New Category') }}" maxlength="255"
                                            {{$lang['status'] == true ? 'required':''}}
                                            @if($lang['status'] == true) oninvalid="document.getElementById('{{$lang['code']}}-link').click()" @endif>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{ $lang['code'] }}">
                                @endforeach
                                @else
                                <div class="row gy">
                                    <div class="col-md-12 mb-4">
                                        <div class="form-group lang_form" id="{{ $defaultLang }}-form">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('name') }}
                                                ({{ strtoupper($defaultLang) }})</label>
                                            <input type="text" name="name[]" class="form-control" maxlength="255"
                                                placeholder="{{ translate('New Category') }}" required>
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $defaultLang }}">
                                        @endif
                                        <input name="position" value="0" class="d--none">
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
                                                           data-maxFileSize="{{ readableUploadMaxFileSize('image') }}" required>
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
                                                           data-maxFileSize="{{ readableUploadMaxFileSize('image') }}" required>
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
                                                <span class="font-medium text-title">(8:1)</span>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- <div class="col-lg-6 from_part_2">
                                        <div class="form-group">
                                            <div class="d-flex gap-1">
                                                <label class="mb-0">{{translate('Image')}}</label>
                                                <small class="text-danger">*</small>
                                            </div>
                                            <div class="d-flex align-items-center mt-4">
                                                <div class="upload-file">
                                                    <input type="file" name="image" class="upload-file__input"
                                                           accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                           data-maxFileSize="{{ readableUploadMaxFileSize('image') }}" required>
                                                    <div class="upload-file__img_drag upload-file__img width-300px max-h-200px overflow-hidden">
                                                        <img width="465" id="viewer" src="{{asset('public/assets/admin/img/icons/upload_img2.png')}}" alt="">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <p class="text-muted mb-0">{{ translate('Image format')}} - {{ implode(', ', array_column(IMAGEEXTENSION, 'key')) }}</p>
                                                <p class="text-muted mb-0">{{ translate("Image Size") }} - {{ translate('maximum size') }} {{ readableUploadMaxFileSize('image') }}</p>
                                                <p class="text-muted mb-0">{{ translate('Image Ratio') }} - 1:1</p>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="col-lg-6 from_part_2">
                                        <div class="form-group">
                                            <div class="d-flex align-items-center gap-1">
                                                <label class="mb-0">{{translate('Banner Image')}}</label>
                                                <small class="text-danger">*</small>
                                            </div>
                                            <div class="d-flex align-items-center mt-4">
                                                <div class="upload-file">
                                                    <input type="file" name="banner_image" class="upload-file__input"
                                                           accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                           data-maxFileSize="{{ readableUploadMaxFileSize('image') }}" required>
                                                    <div class="upload-file__img_drag upload-file__img width-300px max-h-200px overflow-hidden">
                                                        <img width="465" id="viewer" src="{{asset('public/assets/admin/img/icons/upload_img2.png')}}" alt="">
                                                    </div>
                                                </div>
                                            </div>
                                             <div class="mt-2">
                                                 <p class="text-muted mb-0">{{ translate('Image format')}} - {{ implode(', ', array_column(IMAGEEXTENSION, 'key')) }}</p>
                                                 <p class="text-muted mb-0">{{ translate("Image Size") }} - {{ translate('maximum size') }} {{ readableUploadMaxFileSize('image') }}</p>
                                                 <p class="text-muted mb-0">{{ translate('Image Ratio') }} - 8:1</p>
                                             </div>
                                        </div>
                                    </div> -->
                                </div>

                                <div class="d-flex justify-content-end gap-3 mt-4">
                                    <button type="reset" id="reset" class="btn btn-secondary">{{translate('reset')}}</button>
                                    <button type="submit" class="btn btn-primary">{{translate('submit')}}</button>
                                </div>
                            </div>
                        </div>
                    </form>

            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-top px-card pt-4">
                        <div class="row justify-content-between align-items-center gy-2">
                            <div class="col-sm-4 col-md-6 col-lg-8">
                                <h5 class="d-flex gap-1 mb-0">
                                    {{translate('Category_Table')}}
                                    <span class="badge badge-soft-dark rounded-50 fz-12">{{ $categories->total() }}</span>
                                </h5>
                            </div>
                            <div class="col-sm-8 col-md-6 col-lg-4">
                                <form action="{{url()->current()}}" method="GET">
                                    <div class="input-group">
                                        <input id="datatableSearch_" type="search" name="search"
                                            class="form-control"
                                            placeholder="{{translate('Search by category name')}}" aria-label="{{translate('Search')}}"
                                            value="{{$search}}" required autocomplete="off">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">{{translate('Search')}}</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="py-4">
                        <div class="table-responsive datatable-custom">
                            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{translate('SL')}}</th>
                                        <th>{{translate('Category_Image')}}</th>
                                        <th>{{translate('name')}}</th>
                                        <th>{{translate('status')}}</th>
                                        <th>{{translate('priority')}}</th>
                                        <th class="text-center">{{translate('action')}}</th>
                                    </tr>
                                </thead>

                                <tbody>
                                @foreach($categories as $key=>$category)
                                    <tr>
                                        <td>{{$categories->firstitem()+$key}}</td>
                                        <td>
                                            <div>
                                                <img width="50" class="avatar-img rounded" src="{{$category->imageFullPath}}"  alt="">
                                            </div>
                                        </td>
                                        <td><div class="text-capitalize">{{$category['name']}}</div></td>
                                        <td>
                                            <div class="">
                                                <label class="switcher">
                                                    <input class="switcher_input status-change" type="checkbox" {{$category['status']==1? 'checked' : ''}} id="{{$category['id']}}"
                                                           data-url="{{route('admin.category.status',[$category['id'],1])}}"
                                                    >
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="min-w-70">
                                                <select name="priority" class="custom-select redirect-url-value"
                                                        data-url="{{ route('admin.category.priority', ['id' => $category['id'], 'priority' => '']) }}">
                                                    @for($i = 1; $i <= 10; $i++)
                                                        <option value="{{ $i }}" {{ $category->priority == $i ? 'selected' : '' }}>{{ $i }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a class="btn btn-outline-info btn-sm edit square-btn"
                                                href="{{route('admin.category.edit',[$category['id']])}}">
                                                    <i class="tio-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-sm delete square-btn form-alert"
                                                    data-id="category-{{$category['id']}}" data-message="{{translate("Want to delete this")}}">
                                                    <i class="tio-delete"></i>
                                                </button>
                                            </div>
                                            <form action="{{route('admin.category.delete',[$category['id']])}}"
                                                method="post" id="category-{{$category['id']}}">
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
                                {!! $categories->links() !!}
                            </div>
                        </div>

                        @if(count($categories) == 0)
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
{{--    <script src="{{asset('public/assets/admin/js/read-url.js')}}"></script>--}}
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

       function change_priority(id, priority, message) {
           Swal.fire({
               title: '{{translate("Are you sure?")}}',
               text: message,
               type: 'warning',
               showCancelButton: true,
               cancelButtonColor: 'default',
               confirmButtonColor: '#FC6A57',
               cancelButtonText: '{{translate("No")}}',
               confirmButtonText: '{{translate("Yes")}}',
               reverseButtons: true
           }).then((result) => {
               if (result.value) {
                   const csrfToken = $('meta[name="csrf-token"]').attr('content');

                   const formData = new FormData();
                   formData.append('_token', csrfToken);
                   formData.append('id', id);
                   formData.append('priority', priority);

                   $.ajax({
                       url: "{{ route('admin.category.priority') }}",
                       method: "POST",
                       data: formData,
                       processData: false,
                       contentType: false,
                       success: function(response) {
                           toastr.success("{{translate('Priority changed successfully')}}");
                           setTimeout(function() {
                               location.reload();
                           }, 2000);
                       },
                       error: function(xhr) {
                           toastr.error("{{translate('Priority changed failed')}}");
                       }
                   });
               }
           })
       }
    </script>

@endpush
