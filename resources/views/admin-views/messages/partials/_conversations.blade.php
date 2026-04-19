<div class="">
    <div class="media align-items-center gap-3 border rounded p-2 mb-3">
        <div class="avatar">
            <img class="img-fit rounded-circle" src="{{$user->imageFullPath}}" alt="{{ translate('user') }}">
        </div>
        <div>
            <h5 class="mb-0 media-body">{{$user['f_name'].' '.$user['l_name']}}</h5>
            <span class="fz-12">{{$user['phone']}}</span>
        </div>
    </div>

    <div class="chat_conversation">
        <div class="chat_conversation-inner">
            @foreach($convs as $key=>$con)
                @if(($con->message!=null && $con->reply==null) || $con->is_reply == false)
                    <div class="received_msg">
                        @if(isset($con->message))
                            <div class="msg">{{$con->message}}</div>
                            <span class="time_date"> {{\Carbon\Carbon::parse($con->created_at)->format('h:m a | M d')}}</span>
                        @endif
                        <?php try {?>

                        @if($con->image != null && $con->image != "null")
                                <div class="d-flex flex-wrap gap-2 w-fit-content mt-2">
                                @php($image_array = json_decode($con->image, true))
                                @foreach($image_array as $image)
                                    <a href="{{$image}}" data-lightbox="{{$con->id . $image}}">
                                        <img class="rounded img-fit w-60px ratio-1" src="{{$image}}" onerror="this.src='{{asset('public/assets/admin/img/900x400/img1.jpg')}}'" />
                                    </a><br>
                               @endforeach
                                </div>
                                <span class="time_date">  {{\Carbon\Carbon::parse($con->created_at)->format('h:m a | M d')}}</span>
                            @endif
                        <?php }catch (\Exception $e) {
                        } ?>
                    </div>
                @endif
                @if(($con->reply!=null && $con->message==null) || $con->is_reply == true)
                    <div class="outgoing_msg">
                        @if(isset($con->reply))
                            <div class="msg">{{$con->reply}}</div>
                            <span class="time_date">  {{\Carbon\Carbon::parse($con->created_at)->format('h:m a | M d')}}</span>
                        @endif
                        <?php try {?>
                            @if($con->image != null && $con->image != "null")
                                <div class="d-flex flex-wrap gap-2 justify-content-end ml-auto w-fit-content mt-2">
                                @php($image_array = json_decode($con->image, true))
                                @foreach($image_array as $key=>$image)
                                    @php($image_url = $image)
                                    <a href="{{asset('storage/app/public/conversation').'/'.$image_url}}" data-lightbox="{{$con->id . $image_url }}" >
                                        <img class="rounded img-fit w-60px ratio-1" src="{{asset('storage/app/public/conversation').'/'.$image_url}}" onerror="this.src='{{asset('public/assets/admin/img/900x400/img1.jpg')}}'" />
                                    </a>
                                @endforeach
                                </div>
                                <span class="time_date">  {{\Carbon\Carbon::parse($con->created_at)->format('h:m a | M d')}}</span>
                            @endif
                        <?php }catch (\Exception $e) {} ?>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
<form action="javascript:" method="post" id="reply-form">
    @csrf
    <div class="card mt-2">
        <div class="p-2">
            <div class="quill-custom_">
                <textarea class="border-0 w-100" name="reply"></textarea>
            </div>

            <div id="accordion" class="d-flex gap-2 justify-content-end">
                <button class="btn btn-primary btn-sm" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                    {{translate('Upload')}}
                    <i class="tio-upload"></i>
                </button>
                <button type="submit" data-url="{{route('admin.message.store',[$user->id])}}" class="btn btn-sm btn-primary reply-message">
                        {{translate('send')}}
                        <i class="tio-send"></i>
                </button>
            </div>

            <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo"
                 data-parent="#accordion">
                <div class="d-flex flex-wrap mt-3 gap-2 my-chat-coba" id="coba"></div>
            </div>
        </div>
    </div>
</form>

<script src="{{asset('public/assets/admin')}}/js/tags-input.min.js"></script>
<script src="{{asset('public/assets/admin/js/spartan-multi-image-picker.js')}}"></script>

<script>
    "use strict";

    $('.reply-message').click(function() {
        var url = $(this).data('url');
        replyConvs(url);
    });

    $(document).ready(function () {
        $('.chat_conversation').animate({
            scrollTop: $('.chat_conversation-inner').height()
        }, 500);
    });

    spartanMultiImagePicker();

    $('#collapseTwo').on('hidden.bs.collapse', function () {
         $('.spartan_remove_row').trigger('click');
         $('.spartan_item_wrapper').show()
    })

    function spartanMultiImagePicker() {

        let maxSizeReadable = "{{ readableUploadMaxFileSize('image') }}";
        let maxFileSize = 2 * 1024 * 1024;

        if (maxSizeReadable.toLowerCase().includes('mb')) {
            maxFileSize = parseFloat(maxSizeReadable) * 1024 * 1024;
        } else if (maxSizeReadable.toLowerCase().includes('kb')) {
            maxFileSize = parseFloat(maxSizeReadable) * 1024;
        }

        const MAX_COUNT = 4;

        $("#coba").spartanMultiImagePicker({
            fieldName: 'images[]',
            maxCount: MAX_COUNT,
            rowHeight: '10%',
            groupClassName: 'custom-spartan-items',
            maxFileSize: maxFileSize,
            dropFileLabel: "Drop Here",

            onAddRow: function () {
                const wrappers = $("#coba .spartan_item_wrapper");

                if (wrappers.length > MAX_COUNT) {
                    wrappers.last().hide();
                }
            },

            onRemoveRow: function () {
                setTimeout(() => {
                    const wrappers = $("#coba .spartan_item_wrapper");

                    if (wrappers.length > MAX_COUNT) {
                        wrappers.slice(MAX_COUNT).remove();
                    }else {
                        wrappers.last().show();
                    }
                }, 0);
            },

            onExtensionErr: function () {
                toastr.error('{{translate('Please only input png or jpg type file')}}');
            },

            onSizeErr: function () {
                toastr.error('File size must be less than ' + maxSizeReadable);
            }
        });
    }

</script>
