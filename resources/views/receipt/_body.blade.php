{{-- Reusable receipt body. Expects $order (with order_partial_payments, customer, branch, table, placedBy.role).
     Embeds its own barcode via JsBarcode (assumed loaded once on the outer page). --}}
@php
    $shopName  = \App\CentralLogics\Helpers::get_business_settings('restaurant_name') ?? 'Lahab';
    $shopPhone = \App\CentralLogics\Helpers::get_business_settings('phone');
    $shopAddr  = \App\CentralLogics\Helpers::get_business_settings('address');
    $logoFile  = \App\CentralLogics\Helpers::get_business_settings('logo');
    $logoUrl   = $logoFile ? asset('storage/app/public/restaurant/' . $logoFile) : null;
    $subtotal  = 0; $addonTotal = 0;
    $staffName = null;
    if ($order->placedBy) {
        $staffName = trim(($order->placedBy->f_name ?? '') . ' ' . ($order->placedBy->l_name ?? ''));
        if ($order->placedBy->role?->name) $staffName .= ' (' . $order->placedBy->role->name . ')';
    }
@endphp

<div class="r-shop">
    @if($logoUrl)
        <img src="{{ $logoUrl }}" class="r-logo" alt="" onerror="this.style.display='none'">
    @endif
    <div class="r-name">{{ $shopName }}</div>
    @if($order->branch?->name)<div class="r-sub">{{ $order->branch->name }}</div>@endif
    @if($shopAddr)<div class="r-sub">{{ $shopAddr }}</div>@endif
    @if($shopPhone)<div class="r-sub">{{ $shopPhone }}</div>@endif
</div>

<div class="r-head-meta">
    <span>#{{ $order->id }}</span>
    <span>{{ $order->created_at->format('d/m/y · H:i') }}</span>
</div>

<div class="r-meta">
    @if($order->table)
        <div>Table: {{ $order->table->number }}{{ $order->table->zone ? ' · ' . $order->table->zone : '' }}</div>
    @endif
    <div>Type: {{ \App\CentralLogics\Helpers::order_type_label($order->order_type) }}</div>
    @if($order->customer)
        <div>Cust: {{ $order->customer->f_name }} {{ $order->customer->l_name }}</div>
    @endif
    @if($staffName)<div>Served by: {{ $staffName }}</div>@endif
</div>

<div class="r-divider"></div>

<div class="r-items">
    @foreach($order->details as $item)
        @php
            $p = is_array($item->product_details) ? $item->product_details : json_decode($item->product_details, true);
            $lineSubtotal = ($item->price - ($item->discount_on_product ?? 0)) * $item->quantity;
            $subtotal += $lineSubtotal;

            $addonIds    = is_array($item->add_on_ids) ? $item->add_on_ids : json_decode($item->add_on_ids, true);
            $addonQtys   = is_array($item->add_on_qtys) ? $item->add_on_qtys : json_decode($item->add_on_qtys, true);
            $addonPrices = is_array($item->add_on_prices) ? $item->add_on_prices : json_decode($item->add_on_prices, true);
            $lineAddon = 0;
            if (is_array($addonPrices)) {
                foreach ($addonPrices as $i => $aPrice) {
                    $aQty = $addonQtys[$i] ?? 1;
                    $lineAddon += ((float) $aPrice) * ((int) $aQty);
                }
            }
            $addonTotal += $lineAddon;
        @endphp
        <div class="r-item-row">
            <div class="r-item-name">
                {{ $item->quantity }}× {{ $p['name'] ?? 'Item' }}
                @if(!empty($addonIds) && is_array($addonIds))
                    @foreach($addonIds as $i => $aid)
                        @php
                            $aName = collect($p['add_ons'] ?? [])->firstWhere('id', $aid)['name'] ?? (\App\Model\AddOn::find($aid)->name ?? 'Addon');
                            $aQty  = $addonQtys[$i] ?? 1;
                        @endphp
                        <small>+ {{ $aQty }}× {{ $aName }}</small>
                    @endforeach
                @endif
            </div>
            <div class="r-item-amt">{{ \App\CentralLogics\Helpers::set_symbol($lineSubtotal + $lineAddon) }}</div>
        </div>
    @endforeach
</div>

<div class="r-divider"></div>

<div class="r-totals-row"><span>Subtotal</span><span>{{ \App\CentralLogics\Helpers::set_symbol($subtotal + $addonTotal) }}</span></div>
@if($order->total_tax_amount > 0)
    <div class="r-totals-row"><span>Tax</span><span>{{ \App\CentralLogics\Helpers::set_symbol($order->total_tax_amount) }}</span></div>
@endif
@if($order->coupon_discount_amount > 0)
    <div class="r-totals-row"><span>Discount</span><span>− {{ \App\CentralLogics\Helpers::set_symbol($order->coupon_discount_amount) }}</span></div>
@endif
<div class="r-totals-row r-grand"><span>TOTAL</span><span>{{ \App\CentralLogics\Helpers::set_symbol($order->order_amount) }}</span></div>

<div class="r-payments">
    @forelse($order->order_partial_payments ?? [] as $p)
        <div class="r-pay-row">
            <span>{{ ucfirst(str_replace('_', ' ', preg_replace('/^offline:\d+$/', 'offline', $p->paid_with))) }}</span>
            <span>{{ \App\CentralLogics\Helpers::set_symbol($p->paid_amount) }}</span>
        </div>
    @empty
        <div class="r-pay-row">
            <span>Payment</span>
            <span>{{ $order->payment_status === 'paid' ? 'PAID' : 'UNPAID' }}</span>
        </div>
    @endforelse

    @if(($order->bring_change_amount ?? 0) > 0)
        <div class="r-pay-row" style="font-weight:700;">
            <span>Change</span>
            <span>{{ \App\CentralLogics\Helpers::set_symbol($order->bring_change_amount) }}</span>
        </div>
    @endif
</div>

<div class="r-divider"></div>

<div class="r-footer">
    Thank you for dining with us!
    <div class="r-barcode-wrap">
        <svg class="r-barcode" data-code="{{ $order->id }}-{{ $order->receipt_token ?? '' }}"></svg>
        <div class="r-verify">VERIFY: {{ $order->receipt_token ?: $order->id }}</div>
    </div>
</div>
