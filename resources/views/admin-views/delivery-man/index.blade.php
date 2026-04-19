@extends('layouts.admin.app')

@section('title', translate('Add new delivery-man'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img width="20" class="avatar-img" src="{{ asset('public/assets/admin/img/icons/deliveryman.png') }}"
                    alt="">
                <span class="page-header-title">
                    {{ translate('add_New_Deliveryman') }}
                </span>
            </h2>
        </div>

        <div class="row g-2">
            <div class="col-12">
                <form action="{{ route('admin.delivery-man.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0 d-flex align-items-center gap-2 mb-0">
                                {{ translate('Deliveryman_Info') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <div class="card p-4 h-100">
                                        <div class="form-group">
                                            <label class="input-label">{{ translate('first_Name') }}</label>
                                            <input value="{{ old('f_name') }}" type="text" name="f_name"
                                                class="form-control" placeholder="{{ translate('first_Name') }}" tabindex="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="input-label">{{ translate('last_Name') }}</label>
                                            <input value="{{ old('l_name') }}" type="text" name="l_name"
                                                class="form-control" placeholder="{{ translate('last_Name') }}" tabindex="2" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="input-label">{{ translate('phone number') }}</label>
                                            <input value="{{ old('phone') }}" type="text" name="phone"
                                                class="form-control" placeholder="{{ translate('Ex : 017********') }}" tabindex="3"
                                                required>
                                        </div>
                                        <div class="form-group">
                                            <label class="input-label">{{ translate('branch') }}</label>
                                            <select name="branch_id" class="form-control" tabindex="4">
                                                <option value="0">{{ translate('all') }}</option>
                                                @foreach (\App\Model\Branch::all() as $branch)
                                                    <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                </div>
                                <div class="col-md-4">
                                    <div class="card p-4 h-100 d-center">
                                        <div>
                                            <div class="mb-4">
                                                <h4 class="mb-0 text-center">{{ translate('Profile_Image') }} <span class="text-danger">*</span> </h4>
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
                                    <!-- <div class="form-group text-center">
                                        <label class="input-label font-weight-semibold mb-0 d-block">{{ translate('Profile_Image') }}</label>

                                        <div class="upload-file inline-size-180 mx-auto cmn_focus rounded mt-2">
                                            <input type="file" name="image" class="upload-file__input" tabindex="5"
                                                   accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                   data-maxFileSize="{{ readableUploadMaxFileSize('image') }}">
                                            <label class="upload-file-wrapper d-flex justify-content-center align-items-center m-auto">
                                                <div class="upload-file-textbox text-center">
                                                    <img width="34" height="34" src="{{ asset('public/assets/admin/img/document-upload.svg') }}" alt="">
                                                    <h6 class="mt-2 font-weight-semibold text-center">
                                                        <span class="text-c2">{{ translate('Click to upload') }}</span><br>{{ translate('or drag and drop') }}
                                                    </h6>
                                                </div>
                                                <img class="upload-file-img" loading="lazy" style="display: none;" alt="">
                                            </label>
                                        </div>
                                        <div class="mt-2 text-center">
                                            <p class="text-muted mb-0">{{ translate('Image format')}} - {{ implode(', ', array_column(IMAGEEXTENSION, 'key')) }}</p>
                                            <p class="text-muted mb-0">{{ translate("Image Size") }} - {{ translate('maximum size') }} {{ readableUploadMaxFileSize('image') }}</p>
                                            <p class="text-muted mb-0">{{ translate('Image Ratio') }} - 1:1</p>
                                        </div>
                                    </div> -->
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0 d-flex align-items-center gap-2 mb-0">
                                {{ translate('Identity_Info') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="input-label">{{ translate('identity_Type') }}</label>
                                        <select name="identity_type" class="form-control" tabindex="6">
                                            <option value="0" selected disabled>{{ translate('Select Driver Identity type') }}</option>
                                            <option value="passport">{{ translate('passport') }}</option>
                                            <option value="driving_license">{{ translate('driving') }}{{ translate('license') }}</option>
                                            <option value="nid">{{ translate('nid') }}</option>
                                            <option value="restaurant_id">{{ translate('restaurant') }}{{ translate('id') }}</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label class="input-label">{{ translate('identity_Number') }}</label>
                                        <input value="{{ old('identity_number') }}" type="text" name="identity_number"
                                            class="form-control" placeholder="{{ translate('Ex : DH-23434-LS') }}" tabindex="7" required>
                                    </div>

                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="mb-15">
                                            <label class="input-label font-weight-semibold mb-0">{{ translate('identity_Image') }}</label>
                                            <p class="text-muted mb-0">
                                                {{ translate('Image format')}} - {{ implode(', ', array_column(IMAGEEXTENSION, 'key')) }}
                                                {{ translate("Image Size") }} - {{ translate('maximum size') }} {{ readableUploadMaxFileSize('image') }}
                                                {{ translate('Image Ratio') }} - 1:1
                                            </p>
                                        </div>
                                        <div class="image-scroll-wrapper rounded " tabindex="8">
                                            <div class="d-flex gap-3 custom" id="coba">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-3">
                        <div class="card-header">
                            <div>
                                <h5 class="mb-0 d-flex align-items-center gap-2 mb-0">{{ translate('Account_Information') }}</h5>
                                <p class="fz-12 mb-0"> {{ translate('Deliveryman Email & Password') }}</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label">{{ translate('email') }}</label>
                                        <input value="{{ old('email') }}" type="email" name="email"
                                            class="form-control" placeholder="{{ translate('Ex : ex@example.com') }}" tabindex="9" required>
                                    </div>
                                </div>

                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label">{{ translate('password') }}</label>
                                        <div class="input-group input-group-merge">
                                            <input type="password" name="password"
                                                class="js-toggle-password form-control form-control input-field"
                                                id="password" placeholder="{{ translate('Ex: 8+ Characters') }}"
                                                required
                                                data-hs-toggle-password-options='{
                                                "target": "#changePassTarget",
                                                "defaultClass": "tio-hidden-outlined",
                                                "showClass": "tio-visible-outlined",
                                                "classChangeTarget": "#changePassIcon"
                                                }' tabindex="10">
                                            <div id="changePassTarget" class="input-group-append">
                                                <a class="input-group-text" href="javascript:">
                                                    <i id="changePassIcon" class="tio-visible-outlined"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label">{{ translate('confirm_password') }}</label>
                                        <div class="input-group input-group-merge">
                                            <input type="password" name="confirm_password"
                                                class="js-toggle-password form-control form-control input-field"
                                                id="confirm_password" placeholder="{{ translate('confirm password') }}"
                                                required
                                                data-hs-toggle-password-options='{
                                                "target": "#changeConPassTarget",
                                                "defaultClass": "tio-hidden-outlined",
                                                "showClass": "tio-visible-outlined",
                                                "classChangeTarget": "#changeConPassIcon"
                                                }' tabindex="11">
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

                    <div class="d-flex gap-3 justify-content-end mt-3">
                        <button type="reset" id="reset"
                            class="btn btn-secondary" tabindex="12">{{ translate('reset') }}</button>
                        <button type="submit" class="btn btn-primary" tabindex="13">{{ translate('submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/read-url.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
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


        $(function() {
            const $coba = $("#coba");
            const imageWidth = 200;
            const scrollSpeed = 100;

            let maxSizeReadable = "{{ readableUploadMaxFileSize('image') }}";
            let maxFileSize = 2 * 1024 * 1024; // default 2MB

            if (maxSizeReadable.toLowerCase().includes('mb')) {
                maxFileSize = parseFloat(maxSizeReadable) * 1024 * 1024;
            } else if (maxSizeReadable.toLowerCase().includes('kb')) {
                maxFileSize = parseFloat(maxSizeReadable) * 1024;
            }

            // Initialize Spartan Multi Image Picker
            $coba.spartanMultiImagePicker({
                fieldName: 'identity_image[]',
                maxCount: 5,
                rowHeight: '230px',
                groupClassName: 'flex-item mb-0',
                maxFileSize: maxFileSize,
                placeholderImage: {
                    image: '{{ asset('public/assets/admin/img/document-upload.svg') }}',
                    width: '34px',
                },
                dropFileLabel: `
            <h6 id="dropAreaLabel" class="mt-2 font-weight-semibold text-center">
                <span class="text-c2">{{ translate('Click to upload') }}</span>
                <br>
                {{ translate('or drag and drop') }}
            </h6>`,
                onRenderedPreview: function(index) {
                    $("#dropAreaLabel").hide();
                    checkOverflow(); // Check if overflow occurs to toggle arrow visibility
                },
                onRemoveRow: function(index) {
                    checkOverflow(); // Update arrow visibility when an image is removed
                },
                onExtensionErr: function(index, file) {
                    toastr.error('{{ translate('Please only input png or jpg type file') }}', {
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

            // Check if #coba overflows the window width and toggle arrow visibility
            function checkOverflow() {
                const isOverflowing = $coba[0].scrollWidth > $coba[0].clientWidth;
                if (isOverflowing) {
                    $('.arrow-left').show();
                } else {
                    $('.arrow-left').hide();
                }
            }

            // Scroll images to the left and loop when clicking the arrow
            $('.arrow-left').click(function() {
                $coba.animate({
                    'margin-inline-start': `-=${imageWidth}px`
                }, scrollSpeed, function() {
                    // Move the first item to the end for looping effect
                    $coba.children(".spartan_item_wrapper").first().appendTo($coba);
                    $coba.css('margin-inline-start', 0); // Reset the margin after moving
                });
            });

            // Initial check on page load
            checkOverflow();

            // Re-check on window resize to dynamically adjust arrow visibility
            $(window).resize(checkOverflow);
        });




        $('.js-toggle-password').each(function() {
            new HSTogglePassword(this).init()
        });

        $(document).on('ready', function() {
            $('.js-toggle-password').each(function() {
                new HSTogglePassword(this).init()
            });

            $('.js-validate').each(function() {
                $.HSCore.components.HSValidation.init($(this));
            });
        });
    </script>
@endpush
