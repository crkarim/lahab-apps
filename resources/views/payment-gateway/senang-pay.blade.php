@extends('payment-gateway.layouts.master')

@push('script')

@endpush

@section('content')

    @if(isset($config))
        <center><h1>Please do not refresh this page...</h1></center>

        <div class="col-md-6 mb-4" style="cursor: pointer">
            <div class="card">
                <div class="card-body" style="height: 70px">
                    @php
                        $secretkey = $config->secret_key;
                    @endphp
                    @php
                        $data = new \stdClass();
                    @endphp
                    @php
                        $data->merchantId = $config->merchant_id;
                    @endphp
                    @php
                        $data->amount = $payment_data->payment_amount;
                    @endphp
                    @php
                        $data->name = $payer->name??'';
                    @endphp
                    @php
                        $data->email = $payer->email ??'';
                    @endphp
                    @php
                        $data->phone = $payer->phone ??'';
                    @endphp
                    @php
                        $data->hashed_string = md5($secretkey . urldecode($data->amount) );
                    @endphp
                    <form id="form" method="post"
                          action="https://{{env('APP_MODE')=='live'?'app.senangpay.my':'sandbox.senangpay.my'}}/payment/{{$config->merchant_id}}">
                        <input type="hidden" name="amount" value="{{$data->amount}}">
                        <input type="hidden" name="name" value="{{$data->name}}">
                        <input type="hidden" name="email" value="{{$data->email}}">
                        <input type="hidden" name="phone" value="{{$data->phone}}">
                        <input type="hidden" name="hash" value="{{$data->hashed_string}}">
                    </form>

                </div>
            </div>
        </div>
    @endif

    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function () {
            document.getElementById("form").submit();
        });
    </script>
@endsection
