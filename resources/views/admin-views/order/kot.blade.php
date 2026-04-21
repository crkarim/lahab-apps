<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOT {{ $order->kot_number }}</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        body { font-family: -apple-system, "SF Mono", Menlo, Consolas, monospace;
               max-width: 80mm; margin: 0 auto; padding: 10mm 6mm; color: #000;
               background: #fff; }
        .kot-head { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 6px; margin-bottom: 8px; }
        .kot-label { font-size: 12px; letter-spacing: 2px; font-weight: 700; }
        .kot-number { font-size: 44px; font-weight: 800; line-height: 1.05; margin: 2px 0 4px; }
        .kot-reprint { background: #000; color: #fff; padding: 2px 8px;
                       font-size: 11px; font-weight: 700; display: inline-block; letter-spacing: 1.5px; }
        .kot-packaging {
            border: 3px solid #000;
            padding: 6px 8px;
            text-align: center;
            font-weight: 800;
            font-size: 16px;
            letter-spacing: 2px;
            margin: 6px 0 10px;
            line-height: 1.2;
        }
        .kot-packaging small { display: block; font-weight: 600; font-size: 11px; letter-spacing: 1px; margin-top: 2px; }
        .kot-meta { font-size: 13px; margin: 2px 0; }
        .kot-meta strong { font-weight: 700; }
        .items { margin: 8px 0; }
        .item { margin-bottom: 10px; page-break-inside: avoid; }
        .item-main { font-size: 16px; font-weight: 700; }
        .item-qty { display: inline-block; min-width: 28px; }
        .item-sub { font-size: 13px; margin-left: 36px; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .barcode-wrap { text-align: center; margin-top: 10px; }
        #kotBarcode { max-width: 100%; height: 50px; }
        .time-line { text-align: right; font-size: 11px; color: #444; }
        @media print {
            body { padding: 2mm; max-width: 80mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

@php
    // Supplementary print (add-on round) filters out items already cooking.
    $isSupp = isset($isSupplementary) && $isSupplementary;
    $afterId = $afterId ?? null;
    $itemsToPrint = $isSupp && $afterId
        ? $order->details->where('id', '>', $afterId)->values()
        : $order->details;
@endphp

<div class="kot-head">
    <div class="kot-label">
        {{ $isSupp ? 'ADD-ON · KITCHEN ORDER' : 'KITCHEN ORDER TICKET' }}
    </div>
    <div class="kot-number">{{ $order->kot_number }}</div>
    @if($isSupp)
        <div class="kot-reprint" style="background:#E67E22;">FIRE NOW · ADD-ON</div>
    @elseif($isReprint)
        <div class="kot-reprint">REPRINT</div>
    @endif
</div>

@if($order->order_type === 'pos')
    <div class="kot-packaging">
        ⚠ TAKE-AWAY
        <small>Pack for customer pick-up</small>
    </div>
@elseif($order->order_type === 'delivery')
    <div class="kot-packaging">
        ⚠ DELIVERY
        <small>Pack for rider · seal properly</small>
    </div>
@endif

<div class="kot-meta">
    <div><strong>Order:</strong> #{{ $order->id }}</div>
    @if($order->table)
        <div><strong>Table:</strong> {{ $order->table->number }}{{ $order->table->zone ? ' · ' . $order->table->zone : '' }}</div>
    @endif
    @if($order->customer)
        <div><strong>Customer:</strong> {{ trim(($order->customer->f_name ?? '') . ' ' . ($order->customer->l_name ?? '')) ?: 'Guest' }}</div>
    @endif
    @if($order->order_note)
        <div><strong>Note:</strong> {{ $order->order_note }}</div>
    @endif
    @php
        $placedByName = null; $placedByRole = null;
        if ($order->placedBy) {
            $placedByName = trim(($order->placedBy->f_name ?? '') . ' ' . ($order->placedBy->l_name ?? '')) ?: ($order->placedBy->email ?? '');
            $placedByRole = $order->placedBy->role?->name;
        } elseif ($order->branch) {
            $placedByName = $order->branch->name;
            $placedByRole = 'Branch';
        }
    @endphp
    @if($placedByName)
        <div><strong>Placed by:</strong> {{ $placedByName }}{{ $placedByRole ? ' · ' . $placedByRole : '' }}</div>
    @endif
</div>

<div class="divider"></div>

<div class="items">
    @foreach($itemsToPrint as $item)
        @php
            $p = is_array($item->product_details) ? $item->product_details : json_decode($item->product_details, true);
            $variation = is_array($item->variation) ? $item->variation : json_decode($item->variation, true);
            $addonIds  = is_array($item->add_on_ids) ? $item->add_on_ids : json_decode($item->add_on_ids, true);
            $addonQtys = is_array($item->add_on_qtys) ? $item->add_on_qtys : json_decode($item->add_on_qtys, true);
            $productAddons = collect($p['add_ons'] ?? []);
        @endphp
        <div class="item">
            <div class="item-main">
                <span class="item-qty">{{ $item->quantity }}×</span> {{ $p['name'] ?? 'Item' }}
            </div>
            @if(!empty($variation) && is_array($variation))
                @foreach($variation as $v)
                    <div class="item-sub">• {{ $v['type'] ?? $v['Size'] ?? '' }}</div>
                @endforeach
            @endif
            @if(!empty($addonIds) && is_array($addonIds))
                @foreach($addonIds as $i => $addonId)
                    @php
                        $addonName = $productAddons->firstWhere('id', $addonId)['name'] ?? null;
                        if (!$addonName) {
                            $addonModel = \App\Model\AddOn::find($addonId);
                            $addonName = $addonModel->name ?? 'Addon';
                        }
                        $qty = $addonQtys[$i] ?? 1;
                    @endphp
                    <div class="item-sub">+ {{ $qty }}× {{ $addonName }}</div>
                @endforeach
            @endif
        </div>
    @endforeach
</div>

<div class="divider"></div>

<div class="time-line">
    {{ $order->kot_sent_at?->format('d M Y · H:i') ?? now()->format('d M Y · H:i') }}
    · Print #{{ $order->kot_print_count }}
</div>

<div class="barcode-wrap">
    <svg id="kotBarcode"></svg>
</div>

@if($order->order_type === 'delivery' || $order->order_type === 'pos')
    {{-- Orders that leave the counter with their food (online-delivery + take-away)
         get the customer receipt printed right below the KOT. Delivery: rider hands
         it to the customer. Take-away: customer walks out with it. Dine-in orders
         still get their receipt through the Checkout modal at table turn-over. --}}
    <div class="cut-marker">— — — CUT · CUSTOMER RECEIPT — — —</div>
    <div class="r-doc">
        @include('receipt._body', ['order' => $order])
    </div>
    @include('receipt._styles')
@endif

<script>
    try {
        JsBarcode('#kotBarcode', @json($order->kot_number), {
            format: 'CODE128', displayValue: false, width: 2, height: 50, margin: 0
        });
    } catch (e) { /* barcode library failed to load — ignore */ }

    // Render any receipt barcodes added by the customer-receipt section
    document.querySelectorAll('.r-barcode').forEach(function (svg) {
        try { JsBarcode(svg, svg.dataset.code, { format: 'CODE128', displayValue: false, width: 2, height: 40, margin: 0 }); }
        catch (e) {}
    });

    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 450);
    });
    window.addEventListener('afterprint', function () { window.close(); });
</script>

</body>
</html>
