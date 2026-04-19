@extends('layouts.admin.app')

@section('title', translate('Add new sub category'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/category.png')}}" alt="">
                <span class="page-header-title">
                    {{translate('add_New_Sub_Category')}}
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

                        @if($data && array_key_exists('code', $data[0]))
                        <ul class="nav nav-tabs w-fit-content mb-4">
                            @foreach($data as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link {{$lang['default'] == true? 'active':''}}" href="#" id="{{$lang['code']}}-link">{{\App\CentralLogics\Helpers::get_language_name($lang['code']).'('.strtoupper($lang['code']).')'}}</a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                @foreach($data as $lang)
                                    <div class="form-group mb-0 {{$lang['default'] == false ? 'd-none':''}} lang_form" id="{{$lang['code']}}-form">
                                        <label class="input-label">{{translate('sub_category')}} {{translate('name')}} ({{strtoupper($lang['code'])}})</label>
                                        <input type="text" name="name[]" class="form-control" maxlength="255" placeholder="{{ translate('New Sub Category') }}" {{$lang['status'] == true ? 'required':''}}
                                        @if($lang['status'] == true) oninvalid="document.getElementById('{{$lang['code']}}-link').click()" @endif>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang['code']}}">
                                @endforeach
                                @else
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group mb-0 lang_form" id="{{$defaultLang}}-form">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('sub_category')}} {{translate('name')}}({{strtoupper($defaultLang)}})</label>
                                            <input type="text" name="name[]" class="form-control" placeholder="{{ translate('New Sub Category') }}" required>
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{$defaultLang}}">
                                        @endif
                                        <input class="position-area" name="position" value="1">
                                        <input type="hidden" name="type" value="sub_catddsddeegory">
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group mb-0">
                                            <label for="exampleFormControlSelect1" class="input-label">
                                                {{translate('main_category')}}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <select id="exampleFormControlSelect1" name="parent_id" class="form-control js-select2-custom" required>
                                                <option disabled selected>{{\App\CentralLogics\translate('Select a category')}}</option>
                                            @foreach(\App\Model\Category::where(['position'=>0])->get() as $category)
                                                    <option value="{{$category['id']}}">{{$category['name']}}</option>
                                                @endforeach
                                            </select>
                                        </div>
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

                                </div>
                                <div class="d-flex justify-content-end gap-3 mt-4">
                                    <button type="reset" class="btn btn-secondary">{{translate('reset')}}</button>
                                    <button type="submit" class="btn btn-primary">{{translate('submit')}}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

            <div class="mt-3">
                <div class="card">
                    <div class="card-top px-card pt-4">
                        <div class="row justify-content-between align-items-center gy-2">
                            <div class="col-sm-4 col-md-6 col-lg-8">
                                <h5 class="d-flex mb-0 gap-2 align-items-center">
                                    {{translate('Sub_Category_Table')}}
                                    <span class="badge badge-soft-dark rounded-50 fz-12">{{ $categories->total() }}</span>
                                </h5>
                            </div>
                            <div class="col-sm-8 col-md-6 col-lg-4">
                                <form action="{{url()->current()}}" method="GET">
                                    <div class="input-group">
                                        <input id="datatableSearch_" type="search" name="search" class="form-control" placeholder="{{translate('Search by sub category name')}}" aria-label="Search" value="{{$search}}" required="" autocomplete="off">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">{{ translate('Search') }}</button>
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
                                    <th>{{translate('image')}}</th>
                                    <th>{{translate('Main_Category')}}</th>
                                    <th>{{translate('sub_Category')}}</th>
                                    <th>{{translate('status')}}</th>
                                    <th class="text-center">{{translate('action')}}</th>
                                </tr>
                                </thead>
                                <tbody id="set-rows">
                                @foreach($categories as $key=>$category)
                                    <tr>
                                        <td>{{$categories->firstitem()+$key}}</td>
                                        <td>
                                            <div>
                                                <img width="50" class="avatar-img rounded" src="{{$category->imageFullPath}}" alt="">
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                {{$category->parent['name']}}
                                            </div>
                                        </td>

                                        <td>
                                            <div>
                                                {{$category['name']}}
                                            </div>
                                        </td>

                                        <td>

                                                <div>
                                                    <label class="switcher">
                                                        <input class="switcher_input status-change" type="checkbox" {{$category['status']==1? 'checked' : ''}} id="{{$category['id']}}"
                                                               data-url="{{route('admin.category.status',[$category['id'],1])}}"
                                                        >
                                                        <span class="switcher_control"></span>
                                                    </label>
                                                </div>

                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a class="btn btn-outline-info btn-sm edit square-btn"
                                                    href="{{route('admin.category.edit',[$category['id']])}}">
                                                    <i class="tio-edit"></i>
                                                </a>

                                                <button class="btn btn-outline-danger btn-sm delete square-btn form-alert"
                                                    data-id="category-{{$category['id']}}" data-message="{{translate("Want to delete this sub category ?")}}"><i class="tio-delete"></i></button>
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

        $(document).on('ready', function () {
            $('.js-select2-custom').each(function () {
                var select2 = $.HSCore.components.HSSelect2.init($(this));
            });
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

