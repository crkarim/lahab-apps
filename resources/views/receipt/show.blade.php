<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\CentralLogics\Helpers::get_business_settings('restaurant_name') ?? 'Receipt' }} — #{{ $order->id }}</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    @include('receipt._styles')
</head>
{{-- r-receipt-page class enables the @media print body wipe in
     receipt/_styles.blade.php. Embedded receipts (inside KOT) don't
     get this class, so their parent's body styling is preserved. --}}
<body class="r-receipt-page">
<div class="r-doc">
    @include('receipt._body', ['order' => $order])
</div>

<script>
    document.querySelectorAll('.r-barcode').forEach(function (svg) {
        try {
            JsBarcode(svg, svg.dataset.code, { format: 'CODE128', displayValue: false, width: 2, height: 40, margin: 0 });
        } catch (e) { /* barcode lib unavailable */ }
    });
</script>
@if(request('print'))
<script>
    window.addEventListener('load', function () { setTimeout(() => window.print(), 300); });
    window.addEventListener('afterprint', function () { window.close(); });
</script>
@endif
</body>
</html>
