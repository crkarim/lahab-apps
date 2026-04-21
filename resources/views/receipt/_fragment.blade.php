{{-- HTML fragment for the in-modal receipt preview.
     The outer page must have loaded JsBarcode once (see _receipt-modal partial). --}}
@include('receipt._styles')
<div class="r-doc">
    @include('receipt._body', ['order' => $order])
</div>
