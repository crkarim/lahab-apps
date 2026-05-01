<?php
    $subtotal = 0;
    $addonPrice = 0;
    $discount = 0;
    $discountType = 'amount';
    $discountOnProduct = 0;
    $addonTotalTax = 0;
    $totalTax = 0;
    $couponDiscount = 0;

    $cart = session()->get('cart');
    $hasCart = $cart && count($cart) > 0;
    if ($hasCart && isset($cart['discount'])) {
        $discount = $cart['discount'];
        $discountType = $cart['discount_type'];
    }
?>

<div class="bg-white rounded">

    {{-- ─── Cart line items ─────────────────────────────────────── --}}
    <div class="table-responsive pos-cart-table border-0">
        <table class="table table-align-middle mb-0">
            <thead class="text-dark">
                <tr>
                    <th class="text-capitalize border-0 min-w-120">{{translate('item')}}</th>
                    <th class="text-capitalize border-0">{{translate('qty')}}</th>
                    <th class="text-capitalize border-0">{{translate('price')}}</th>
                    <th class="text-capitalize border-0 text-center">{{translate('delete')}}</th>
                </tr>
            </thead>
            <tbody>
            @if($hasCart)
                @foreach($cart as $key => $cartItem)
                @if(is_array($cartItem))
                    <?php
                        $productSubtotal = ($cartItem['price']) * $cartItem['quantity'];
                        $discountOnProduct += ($cartItem['discount'] * $cartItem['quantity']);
                        $subtotal += $productSubtotal;
                        $addonPrice += $cartItem['addon_price'];
                        $addonTotalTax += $cartItem['addon_total_tax'];
                        $product = \App\Model\Product::find($cartItem['id']);
                        $totalTax += \App\CentralLogics\Helpers::new_tax_calculate($product, $cartItem['price'], $cartItem['discount_data']) * $cartItem['quantity'];
                    ?>
                    <tr>
                        <td>
                            <div class="media min-w-150px align-items-center gap-10">
                                <img class="avatar avatar-sm"
                                     src="{{asset('storage/app/public/product')}}/{{$cartItem['image']}}"
                                     onerror="this.src='{{asset('public/assets/admin/img/160x160/img2.jpg')}}'"
                                     alt="{{$cartItem['name']}} image">
                                <div class="media-body">
                                    <h5 class="text-hover-primary mb-0">{{Str::limit($cartItem['name'], 10)}}</h5>
                                    <small>{{Str::limit($cartItem['variant'], 20)}}</small>
                                    <small class="d-block">
                                        @php
                                            $addOnQtys = $cartItem['add_on_qtys'];
                                        @endphp
                                        @foreach($cartItem['add_ons'] as $key2 => $id)
                                            @php
                                                $addon = \App\Model\AddOn::find($id);
                                            @endphp
                                            @if($key2 == 0)<strong><u>Addons : </u></strong>@endif

                                            @if($addOnQtys == null)
                                                @php
                                                    $addOnQty = 1;
                                                @endphp
                                            @else
                                                @php
                                                    $addOnQty = $addOnQtys[$key2];
                                                @endphp
                                            @endif

                                            <div class="font-size-sm text-body">
                                                <span>{{$addon['name']}} :  </span>
                                                <span class="font-weight-bold">
                                                    {{ $addOnQty }} x {{ Helpers::set_symbol($addon['price']) }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <input type="number" class="form-control qty" data-key="{{$key}}" value="{{$cartItem['quantity']}}" min="1" onkeyup="updateQuantity(event)">
                        </td>
                        <td>
                            <div>{{ Helpers::set_symbol($productSubtotal) }}</div>
                        </td>
                        <td class="justify-content-center gap-2">
                            <a href="javascript:removeFromCart({{$key}})" class="btn mx-auto btn-sm btn-outline-danger square-btn form-control">
                                <i class="tio-delete"></i>
                            </a>
                        </td>
                    </tr>
                @endif
                @endforeach
            @else
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">
                        <div style="font-size:13px; color:#8e8e93;">
                            {{ translate('Your cart is empty — tap a product to add it') }}
                        </div>
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>

    <?php
        $total = $subtotal + $addonPrice;
        $discountAmount = ($discountType == 'percent' && $discount > 0) ? (($total * $discount) / 100) : $discount;
        $discountAmount += $discountOnProduct;
        $total -= $discountAmount;

        $couponDiscount = session()->get('cart')['coupon_discount'] ?? 0;
        $couponCode = session()->get('cart')['coupon_code'] ?? null;

        if ($couponCode != null) {
            $sessionCustomerId = session()->get('customer_id') ?? null;
            $isGuest = $sessionCustomerId == null ? 1 : 0;
            $couponData = applyPOSCoupon(code: $couponCode, amount: $total, userId: $sessionCustomerId, isGuest: $isGuest);
            $couponCode = $couponData['code'];
            $couponDiscount = $couponData['coupon_discount_amount'];
        }
        if ($couponDiscount) { $total -= $couponDiscount; }

        $extraDiscount = session()->get('cart')['extra_discount'] ?? 0;
        $extraDiscountType = session()->get('cart')['extra_discount_type'] ?? 'amount';
        if ($extraDiscountType == 'percent' && $extraDiscount > 0) {
            $extraDiscountAmount = ($total * $extraDiscount) / 100;
        } else {
            $extraDiscountAmount = min($total, $extraDiscount);
        }
        if ($extraDiscountAmount > 0) { $total -= $extraDiscountAmount; }

        $deliveryCharge = 0;
        if (session()->get('order_type') == 'home_delivery') {
            $distance = 0; $areaId = 1;
            if (session()->has('address')) {
                $address = session()->get('address');
                $distance = $address['distance'];
                $areaId = $address['area_id'];
            }
            $deliveryCharge = \App\CentralLogics\Helpers::get_delivery_charge(
                branchId: session('branch_id') ?? 1,
                distance: $distance,
                selectedDeliveryArea: $areaId,
                orderAmount: $total + $totalTax + $addonTotalTax
            );
        }

        $totalProductAndAddonPriceAfterDiscount = $subtotal + $addonPrice - $discountAmount;
        $totalOrderAmount = $total + $totalTax + $addonTotalTax + $deliveryCharge;

        $taxTotal = round($totalTax + $addonTotalTax, 2);
        $discountTotal = round($discountAmount, 2);
    ?>

    {{-- ─── Totals — only show rows that carry value ──────────────── --}}
    <div class="pos-data-table px-10px pb-2">
        <dl class="row m-0">

            <dt class="col-6">{{translate('subtotal')}}</dt>
            <dd class="col-6 text-right">{{ \App\CentralLogics\Helpers::set_symbol($subtotal + $addonPrice) }}</dd>

            @if($addonPrice > 0)
                <dt class="col-6">{{translate('addon')}}</dt>
                <dd class="col-6 text-right">{{ Helpers::set_symbol($addonPrice) }}</dd>
            @endif

            @if($discountTotal > 0)
                <dt class="col-6">{{translate('product')}} {{translate('discount')}}</dt>
                <dd class="col-6 text-right text-danger">− {{ Helpers::set_symbol($discountTotal) }}</dd>
            @endif

            <dt class="col-6 d-flex align-items-center gap-1">
                {{translate('coupon')}}
                @if($couponCode)
                    <button class="btn btn-sm p-0 text-danger remove-session-coupon" type="button" title="{{ translate('Remove Coupon') }}">
                        <i class="tio-delete"></i>
                    </button>
                @endif
                <button class="btn btn-sm text-c2 p-0 open-coupon-modal" type="button" data-toggle="modal" data-target="#add-coupon-discount" title="{{ translate('Apply Coupon') }}">
                    <i class="tio-edit"></i>
                </button>
            </dt>
            <dd class="col-6 text-right {{ $couponDiscount > 0 ? 'text-danger' : '' }}">
                @if($couponDiscount > 0) − @endif {{ Helpers::set_symbol($couponDiscount) }}
            </dd>

            <dt class="col-6 d-flex align-items-center gap-1">
                {{translate('extra')}} {{translate('discount')}}
                <button class="btn btn-sm text-c2 p-0" type="button" data-toggle="modal" data-target="#add-discount" title="{{ translate('Extra Discount') }}">
                    <i class="tio-edit"></i>
                </button>
            </dt>
            <dd class="col-6 text-right {{ $extraDiscountAmount > 0 ? 'text-danger' : '' }}">
                @if($extraDiscountAmount > 0) − @endif {{ Helpers::set_symbol($extraDiscountAmount) }}
            </dd>

            @if($taxTotal > 0)
                <dt class="col-6">{{translate('VAT/TAX')}}</dt>
                <dd class="col-6 text-right">{{ Helpers::set_symbol($taxTotal) }}</dd>
            @endif

            @if($deliveryCharge > 0)
                <dt class="col-6">{{translate('Delivery Charge')}}</dt>
                <dd class="col-6 text-right">{{ Helpers::set_symbol(round($deliveryCharge, 2)) }}</dd>
            @endif

            <dt class="col-6 pos-total-row">{{translate('total')}}</dt>
            <dd class="col-6 text-right pos-total-row">{{ Helpers::set_symbol(round($totalOrderAmount, 2)) }}</dd>
        </dl>
    </div>
</div>

{{-- ─── Place-order form (Paid By / Collect cash / CTAs) ──────────────── --}}
<div class="pos-data-table pb-130px">
    <form action="{{route('admin.pos.order')}}" id='order_place' method="post">
        @csrf

        {{-- Mirror of the order-type radio so POST carries the current selection
             even when the user never changed it (dine-in is pre-checked by default).
             Previously the session alone decided order_type, and an untouched
             default radio meant session stayed empty → controller fell back to
             'take_away' → stored as 'pos' → KOT showed TAKE-AWAY for dine-in. --}}
        <input type="hidden" name="order_type" id="form_order_type"
               value="{{ session('order_type', 'dine_in') }}">

        <div class="pos-paid-by">
            <div class="pos-label">{{translate('Paid_By')}}</div>
            <?php $ot = session('order_type', 'dine_in'); ?>
            <ul class="option-buttons">
                <li id="cash_payment_li" class="{{ $ot != 'home_delivery' ? '' : 'd-none' }}">
                    <input type="radio" class="paid-by" id="cash" value="cash" name="type" {{ $ot == 'take_away' ? 'checked' : '' }}>
                    <label for="cash" class="btn btn-bordered px-4 mb-0">{{translate('Cash')}}</label>
                </li>
                <li id="card_payment_li" class="{{ $ot != 'home_delivery' ? '' : 'd-none' }}">
                    <input type="radio" class="paid-by" value="card" id="card" name="type">
                    <label for="card" class="btn btn-bordered px-4 mb-0">{{translate('Card')}}</label>
                </li>
                <li id="pay_after_eating_li" class="{{ $ot == 'dine_in' ? '' : 'd-none' }}">
                    <input type="radio" class="paid-by" value="pay_after_eating" id="pay_after_eating" name="type" {{ $ot == 'dine_in' ? 'checked' : '' }}>
                    <label for="pay_after_eating" class="btn btn-bordered px-4 mb-0">{{translate('pay_after_eating')}}</label>
                </li>
                <li id="cash_on_delivery_li" class="{{ $ot == 'home_delivery' ? '' : 'd-none' }}">
                    <input type="radio" class="paid-by" value="cash_on_delivery" id="cash_on_delivery" name="type" {{ $ot == 'home_delivery' ? 'checked' : '' }}>
                    <label for="cash_on_delivery" class="btn btn-bordered px-4 mb-0">{{translate('cash_on_delivery')}}</label>
                </li>
                <li id="wallet_payment_li" class="d-none">
                    <input type="radio" class="paid-by" value="wallet_payment" id="wallet_payment" name="type">
                    <label for="wallet_payment" class="btn btn-bordered px-4 mb-0">{{translate('wallet')}}</label>
                </li>
            </ul>
        </div>

        {{-- Phase 8.5 — Specific cash account picker.
             Loads the active accounts in the viewer's branch scope.
             JS filters to the relevant subset based on the chosen
             payment method (cash → cash-type accounts, card → bank-type,
             etc.). Auto-selects when only one option matches so the
             cashier doesn't have to tap twice. --}}
        @php
            $branchId = auth('admin')->user()?->branch_id;
            $isMaster = (int) (auth('admin')->user()?->admin_role_id ?? 0) === 1;
            try {
                $posCashAccounts = \App\Models\CashAccount::query()
                    ->where('is_active', true)
                    ->when(!$isMaster && $branchId, fn ($q) => $q->where(function ($qq) use ($branchId) {
                        $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    }))
                    ->orderBy('sort_order')->orderBy('name')->get();
            } catch (\Throwable $e) {
                $posCashAccounts = collect(); // pre-migration fallback
            }
        @endphp
        @if($posCashAccounts->count() > 0)
        <div class="pos-paid-by" id="pos-account-picker-wrap" style="margin-top:10px;">
            <div class="pos-label">{{ translate('Specific account') }}</div>
            <select name="cash_account_id" id="pos-cash-account-id" class="form-control" style="font-size:13px;">
                <option value="">— {{ translate('auto-select') }} —</option>
                @foreach($posCashAccounts as $acc)
                    @php
                        $emoji = match($acc->type) {
                            'cash' => '💵', 'bank' => '🏦', 'mfs' => '📱', 'cheque' => '🧾', default => '•',
                        };
                    @endphp
                    <option value="{{ $acc->id }}"
                            data-type="{{ $acc->type }}"
                            data-provider="{{ strtolower($acc->provider ?? '') }}">
                        {{ $emoji }} {{ strtoupper($acc->type) }} · {{ $acc->name }}@if($acc->account_number) · {{ $acc->account_number }}@endif
                    </option>
                @endforeach
            </select>
            <small style="color:#6A6A70; font-size:11px; display:block; margin-top:4px;">
                {{ translate('Pick the exact bank/bKash account where the customer is paying. Leave on auto-select to use the first matching active account.') }}
            </small>
        </div>
        @endif

        {{-- Wallet panels — visibility controlled by JS --}}
        <div class="customer-wallet-info-card d-none">
            <p class="mb-0 fs-13">
                {{ translate('Available balance') }} :
                <span class="font-weight-bold available-wallet-balance"></span>
                <span class="text-danger used-wallet-amount"></span>
            </p>
        </div>

        <div class="wallet-remaining-card d-none">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="balance-label text-dark">{{ translate('Paid Amount') }}</span>
                <span class="paid-by-wallet-amount font-weight-bold"></span>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="balance-label text-dark">{{ translate('Remaining Balance') }}</span>
                <span class="remaining-order-amount font-weight-bold"></span>
            </div>
            <div class="text-dark mb-2 fs-13">{{ translate('Pay Remaining Balance By') }}</div>
            <div class="form-group mb-0">
                <ul class="option-buttons">
                    <li id="wallet_with_cash_li">
                        <input type="radio" class="wallet-remaining-paid-by" value="cash" id="wallet-cash" name="wallet_partial_payment_method">
                        <label for="wallet-cash" class="btn btn-bordered px-4 mb-0">{{translate('Cash')}}</label>
                    </li>
                    <li id="wallet_with_card_li">
                        <input type="radio" class="wallet-remaining-paid-by" value="card" id="wallet-card" name="wallet_partial_payment_method">
                        <label for="wallet-card" class="btn btn-bordered px-4 mb-0">{{translate('Card')}}</label>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Collect-cash inputs — aligned, hidden for pay_after_eating / home_delivery per JS --}}
        <div class="collect-cash-section" style="display: {{ session('order_type') != 'home_delivery' ? 'block' : 'none' }}">
            <div class="form-group d-flex align-items-center justify-content-between gap-2">
                <label class="mb-0 flex-shrink-0" style="min-width:110px;">{{ translate('Paid Amount') }}</label>
                <input type="number" class="form-control text-right" name="paid_amount" step="0.01" id="paid-amount" value="{{ round($totalOrderAmount, 2) }}" onkeyup="calculateAmountDifference()" required>
                <input type="hidden" class="hidden-paid-amount" value="{{ round($totalOrderAmount, 2) }}">
                <input type="hidden" id="hiddenProductAndAddonPriceAfterDiscount" value="{{ round($totalProductAndAddonPriceAfterDiscount, 2) }}">
            </div>
            <div class="form-group d-flex align-items-center justify-content-between gap-2 mb-0">
                <label class="due-or-change-amount mb-0 flex-shrink-0" style="min-width:110px;">{{ translate('Change Amount') }}</label>
                <input type="number" class="form-control text-right" id="amount-difference" value="0" step="0.01" readonly required>
            </div>
        </div>

        <div class="pos-order-btn mt-3">
            <div class="row gy-2 gx-2">
                <div class="col-6">
                    <a href="#" class="btn btn-block empty-cart-button cancel-order-btn">
                        <i class="fa fa-times-circle"></i> {{translate('Cancel_Order')}}
                    </a>
                </div>
                <div class="col-6">
                    <button type="submit" class="btn btn-block order-place-btn">
                        <i class="fa fa-shopping-bag"></i>
                        {{translate('Place_Order')}}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Modals (discount / tax / coupon / remove-coupon) moved to
     admin-views/pos/_cart-modals.blade.php and included once at page level
     in pos/index.blade.php so they survive cart AJAX re-renders. --}}

<script>
    "use strict";

    // Phase 8.5 — Filter the cash-account picker by the selected
    // payment method. cash → type=cash, card → type=bank, no specific
    // method (pay_after_eating / cash_on_delivery / wallet) → hide picker.
    // When exactly one account matches, auto-select it so the cashier
    // doesn't have to interact with the dropdown.
    function lhFilterPosAccounts() {
        var wrap = document.getElementById('pos-account-picker-wrap');
        var sel  = document.getElementById('pos-cash-account-id');
        if (!wrap || !sel) return;

        var method = ($('input[name="type"]:checked').val() || '').toLowerCase();
        // Methods that don't move money to a specific account at place-order
        // time → hide picker.
        if (method === 'pay_after_eating' || method === 'cash_on_delivery' || method === 'wallet_payment') {
            wrap.style.display = 'none';
            sel.value = '';
            return;
        }
        wrap.style.display = '';

        var allowedTypes = method === 'cash' ? ['cash'] : (method === 'card' ? ['bank'] : ['mfs']);
        var firstMatchValue = '';
        var matchCount = 0;
        Array.from(sel.options).forEach(function (opt) {
            if (!opt.value) { opt.hidden = false; return; } // keep "auto-select"
            var t = opt.getAttribute('data-type');
            var ok = allowedTypes.indexOf(t) !== -1;
            opt.hidden = !ok;
            if (ok) {
                matchCount++;
                if (!firstMatchValue) firstMatchValue = opt.value;
            }
        });
        // Single match → preselect for the cashier; multiple → leave on auto.
        if (matchCount === 1) sel.value = firstMatchValue;
        else if (sel.value) {
            // If currently-selected option is now hidden, fall back to auto.
            var current = sel.options[sel.selectedIndex];
            if (current && current.hidden) sel.value = '';
        }
    }
    $(document).on('change', 'input[name="type"]', lhFilterPosAccounts);
    $(document).ready(lhFilterPosAccounts);

    function calculateAmountDifference() {
        let paidAmountStr = $('#paid-amount').val().replace(/[^0-9.]/g, '');
        let paidAmount = parseFloat(paidAmountStr) || 0;
        let orderAmount = {{ $totalOrderAmount }};
        let difference = paidAmount - orderAmount;

        let label = $('.due-or-change-amount');
        let differenceInput = $('#amount-difference');
        let placeOrderButton = $('.order-place-btn');

        if (paidAmount >= orderAmount) {
            label.text('{{ translate("Change Amount") }}');
            differenceInput.val(difference.toFixed(2));
            placeOrderButton.prop('disabled', false);
        } else {
            label.text('{{ translate("Due Amount") }}');
            differenceInput.val(difference.toFixed(2));
            placeOrderButton.prop('disabled', true);
        }
    }

    $('.paid-by').change(function() {
        var selectedPaymentOption = $(this).val();
        let $customerWalletInfoCard = $('.customer-wallet-info-card');
        let $customerWalletRemainingCard = $('.wallet-remaining-card');
        let $customerCard = $('.customer-card');

        let currencySymbol = '{{ \App\CentralLogics\Helpers::currency_symbol() }}';
        let selectedCustomerId = '{{ session("customer_id") ?? "" }}';

        var totalOrderAmount = $('.hidden-paid-amount').val();

        if (selectedPaymentOption == 'pay_after_eating' || selectedPaymentOption === 'wallet_payment') {
            $('.collect-cash-section').addClass('d-none');
        } else {
            $('.collect-cash-section').removeClass('d-none');
        }

        if (selectedPaymentOption == 'wallet_payment') {
            $customerWalletInfoCard.removeClass('d-none');

            let totalOrderAmount = parseFloat($('.hidden-paid-amount').val()) || 0;
            let walletAmount = parseFloat(($customerCard.find('input[name="available_wallet_balance"]')).val()) || 0;

            let remainingAmount = totalOrderAmount - walletAmount;
            let usedWalletAmount = walletAmount > totalOrderAmount ? totalOrderAmount : walletAmount;

            remainingAmount = remainingAmount.toFixed(2);
            usedWalletAmount = usedWalletAmount.toFixed(2);
            walletAmount = walletAmount.toFixed(2);

            $customerWalletRemainingCard.find('.paid-by-wallet-amount').text(walletAmount + currencySymbol);
            $customerWalletRemainingCard.find('.remaining-order-amount').text(remainingAmount + currencySymbol);

            $customerWalletInfoCard.find('.available-wallet-balance').text(walletAmount + currencySymbol);
            $customerWalletInfoCard.find('.used-wallet-amount').text('(Used ' + usedWalletAmount + currencySymbol + ')');

            if (selectedCustomerId && totalOrderAmount > 0 && totalOrderAmount > walletAmount) {
                $customerWalletRemainingCard.removeClass('d-none');
            } else {
                $customerWalletRemainingCard.addClass('d-none');
            }

        } else {
            $customerWalletInfoCard.addClass('d-none');
            $customerWalletRemainingCard.addClass('d-none');
        }

        if (selectedPaymentOption == 'card') {
            $('#paid-amount').attr('readonly', true);
            $('#paid-amount').addClass('bg-F5F5F5');
            $('#paid-amount').val(totalOrderAmount);
            calculateAmountDifference();
        } else {
            $('#paid-amount').removeAttr('readonly');
            $('#paid-amount').removeClass('bg-F5F5F5');
        }
    });

    $(document).ready(function() {
        calculateAmountDifference();
    });

    $(document).ready(function() {
        let customerId = '{{ session('customer_id') }}';
        applyWalletVisibility(customerId);
    });

    $(document).ready(function(){
        function initCouponSlider() {
            const $container = $('.coupon-inner');
            const $btnPrevWrap = $('.button-prev');
            const $btnNextWrap = $('.button-next');
            const $prevBtn = $('.btn-click-prev');
            const $nextBtn = $('.btn-click-next');
            const $item = $('.coupon-slide_items').first();

            if (!$container.length) return;
            const show = $el => $el.css('display', 'flex');
            const hide = $el => $el.css('display', 'none');

            hide($btnPrevWrap);
            hide($btnNextWrap);
            function updateArrows() {
                if (!$container[0]) return;
                const scrollLeft = Math.ceil($container.scrollLeft());
                const clientWidth = $container[0].clientWidth;
                const scrollWidth = $container[0].scrollWidth;
                const maxScroll = Math.max(0, scrollWidth - clientWidth);

                if (maxScroll <= 0) {
                    hide($btnPrevWrap);
                    hide($btnNextWrap);
                    return;
                }

                if (scrollLeft > 0) show($btnPrevWrap);
                else hide($btnPrevWrap);

                if (scrollLeft < maxScroll - 1) show($btnNextWrap);
                else hide($btnNextWrap);
            }
            function getItemWidth() {
                if ($item.length) return $item.outerWidth() || 0;
                return Math.round($container.innerWidth() * 0.48);
            }
            $prevBtn.off('click').on('click', function () {
                const w = getItemWidth();
                const target = Math.max(0, $container.scrollLeft() - w);
                $container.animate({ scrollLeft: target }, 300, updateArrows);
            });

            $nextBtn.off('click').on('click', function () {
                const w = getItemWidth();
                const max = Math.max(0, $container[0].scrollWidth - $container.innerWidth());
                const target = Math.min(max, $container.scrollLeft() + w);
                $container.animate({ scrollLeft: target }, 300, updateArrows);
            });
            $container.on('scroll', updateArrows);
            let resizeTimer;
            $(window).off('resize.coupon').on('resize.coupon', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(updateArrows, 80);
            });

            try {
                const mo = new MutationObserver(() => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(updateArrows, 80);
                });
                mo.observe($container[0], { childList: true, subtree: true });
            } catch (e) {  }

            try {
                const ro = new ResizeObserver(() => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(updateArrows, 80);
                });
                ro.observe($container[0]);
            } catch (e) {  }

            requestAnimationFrame(() => requestAnimationFrame(updateArrows));
        }

        initCouponSlider();
    });
</script>
