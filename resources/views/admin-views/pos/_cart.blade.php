<div class="bg-color4 rounded p-10px mb-20">
    <div class="bg-white rounded overflow-hidden">
        <div class="table-responsive pos-cart-table border">
            <table class="table table-align-middle mb-0">
                <thead class="text-dark bg-light">
                    <tr>
                        <th class="text-capitalize border-0 min-w-120">{{translate('item')}}</th>
                        <th class="text-capitalize border-0">{{translate('qty')}}</th>
                        <th class="text-capitalize border-0">{{translate('price')}}</th>
                        <th class="text-capitalize border-0 text-center">{{translate('delete')}}</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $subtotal = 0;
                    $addonPrice = 0;
                    $discount = 0;
                    $discountType = 'amount';
                    $discountOnProduct = 0;
                    $addonTotalTax =0;
                    $totalTax = 0;
                    $couponDiscount = 0

                ?>
                @if(session()->has('cart') && count( session()->get('cart')) > 0)
                    <?php
                        $cart = session()->get('cart');
                        if(isset($cart['discount']))
                        {
                            $discount = $cart['discount'];
                            $discountType = $cart['discount_type'];
                        }
                    ?>
                    @foreach(session()->get('cart') as $key => $cartItem)

                    @if(is_array($cartItem))

                        <?php
                        $productSubtotal = ($cartItem['price'])*$cartItem['quantity'];
                        $discountOnProduct += ($cartItem['discount']*$cartItem['quantity']);
                        $subtotal += $productSubtotal;
                        $addonPrice += $cartItem['addon_price'];
                        $addonTotalTax += $cartItem['addon_total_tax'];
                        $product = \App\Model\Product::find($cartItem['id']);
                        $totalTax += \App\CentralLogics\Helpers::new_tax_calculate($product, $cartItem['price'], $cartItem['discount_data']) * $cartItem['quantity'];

                        ?>
                        <tr>
                            <td>
                                <div class="media min-w-150px align-items-center gap-10">
                                    <img class="avatar avatar-sm" src="{{asset('storage/app/public/product')}}/{{$cartItem['image']}}"
                                            onerror="this.src='{{asset('public/assets/admin/img/160x160/img2.jpg')}}'" alt="{{$cartItem['name']}} image">
                                    <div class="media-body">
                                        <h5 class="text-hover-primary mb-0">{{Str::limit($cartItem['name'], 10)}}</h5>
                                        <small>{{Str::limit($cartItem['variant'], 20)}}</small>
                                        <small class="d-block">
                                            @php($addOnQtys=$cartItem['add_on_qtys'])
                                            @foreach($cartItem['add_ons'] as $key2 =>$id)
                                                @php($addon=\App\Model\AddOn::find($id))
                                                @if($key2==0)<strong><u>Addons : </u></strong>@endif

                                                @if($addOnQtys==null)
                                                    @php($addOnQty=1)
                                                @else
                                                    @php($addOnQty=$addOnQtys[$key2])
                                                @endif

                                                <div class="font-size-sm text-body">
                                                    <span>{{$addon['name']}} :  </span>
                                                    <span class="font-weight-bold">
                                                        {{ $addOnQty}} x {{Helpers::set_symbol($addon['price']) }}
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
                                <div class="">
                                    {{Helpers::set_symbol($productSubtotal) }}
                                </div>
                            </td>
                            <td class="justify-content-center gap-2">
                                <a href="javascript:removeFromCart({{$key}})" class="btn mx-auto btn-sm btn-outline-danger square-btn form-control">
                                    <i class="tio-delete"></i>
                                </a>
                            </td>
                        </tr>
                    @endif
                    @endforeach
                @endif
                </tbody>
            </table>
        </div>
        <?php
            $total = $subtotal + $addonPrice;
            $discountAmount = ($discountType=='percent' && $discount>0)?(($total * $discount)/100):$discount;
            $discountAmount += $discountOnProduct;
            $total -= $discountAmount;

            $couponDiscount = session()->get('cart')['coupon_discount'] ?? 0;
            $couponCode = session()->get('cart')['coupon_code'] ?? null;

            if ($couponCode != null){
                $sessionCustomerId = session()->get('customer_id') ?? null;
                $isGuest = $sessionCustomerId == null ? 1 : 0;

                $couponData = applyPOSCoupon(code: $couponCode, amount: $total, userId: $sessionCustomerId, isGuest: $isGuest);

                $couponCode = $couponData['code'];
                $couponDiscount = $couponData['coupon_discount_amount'];
            }

            if($couponDiscount) {
                $total -= $couponDiscount;
            }

            $extraDiscount = session()->get('cart')['extra_discount'] ?? 0;
            $extraDiscountType = session()->get('cart')['extra_discount_type'] ?? 'amount';

            if($extraDiscountType == 'percent' && $extraDiscount > 0){
                $extraDiscountAmount = ($total * $extraDiscount) / 100;
            }else{
                $extraDiscountAmount = min($total, $extraDiscount);
            }

            if($extraDiscountAmount > 0) {
                $total -= $extraDiscountAmount;
            }

            $deliveryCharge = 0;
            if (session()->get('order_type') == 'home_delivery'){
                $distance = 0;
                $areaId = 1;
                if (session()->has('address')){
                    $address = session()->get('address');
                    $distance = $address['distance'];
                    $areaId = $address['area_id'];
                }
                $deliveryCharge = \App\CentralLogics\Helpers::get_delivery_charge(branchId: session('branch_id') ?? 1, distance:  $distance, selectedDeliveryArea: $areaId, orderAmount: $total+$totalTax+$addonTotalTax);
            }else{
                $deliveryCharge = 0;
            }
        ?>
        <div class="pos-data-table p-3">
            <dl class="row">
                <dt  class="col-6 text-title">{{translate('addon')}} : </dt>
                <dd class="col-6 text-right">{{Helpers::set_symbol($addonPrice) }}</dd>

                <dt  class="col-6 text-title">{{translate('subtotal')}} : </dt>
                <dd class="col-6 text-right">{{\App\CentralLogics\Helpers::set_symbol($subtotal+$addonPrice) }}</dd>

                <dt  class="col-6 text-title">{{translate('product')}} {{translate('discount')}} :</dt>
                <dd class="col-6 text-right">- {{Helpers::set_symbol(round($discountAmount,2)) }}</dd>

                <dt  class="col-6 text-title">{{translate('coupon')}} {{translate('discount')}} :</dt>
                <dd class="col-6 text-right text-c2 text-nowrap">
                    @if($couponCode)
                        <button class="btn btn-sm p-0 text-danger remove-session-coupon" type="button">
                            <i class="tio-delete"></i>
                        </button>
                    @endif

                    <button class="btn btn-sm text-c2 p-0 open-coupon-modal" type="button" data-toggle="modal" data-target="#add-coupon-discount">
                        <i class="tio-edit"></i>
                    </button>
                    - {{Helpers::set_symbol($couponDiscount) }}
                </dd>

                <dt  class="col-6 text-title">{{translate('extra')}} {{translate('discount')}} :</dt>
                <dd class="col-6 text-right text-c2">
                    <button class="btn btn-sm text-c2 p-0" type="button" data-toggle="modal" data-target="#add-discount">
                        <i class="tio-edit"></i>
                    </button>
                    - {{Helpers::set_symbol($extraDiscountAmount) }}
                </dd>

                <dt  class="col-6 text-title">{{translate('VAT/TAX:')}} : </dt>
                <dd class="col-6 text-right">{{Helpers::set_symbol(round($totalTax + $addonTotalTax,2)) }}</dd>

                <dt  class="col-6 text-title">{{translate('Delivery Charge')}} :</dt>
                <dd class="col-6 text-right"> {{Helpers::set_symbol(round($deliveryCharge,2)) }}</dd>

                @php($totalProductAndAddonPriceAfterDiscount = $subtotal+$addonPrice-$discountAmount)
                @php($totalOrderAmount = $total+$totalTax+$addonTotalTax+$deliveryCharge)
                <dt  class="col-6 border-top font-weight-bold pt-2">{{translate('total')}} : </dt>
                <dd class="col-6 text-right border-top font-weight-bold pt-2">{{Helpers::set_symbol(round($totalOrderAmount, 2)) }}</dd>
            </dl>
        </div>
    </div>
</div>
<div class="pos-data-table pb-130px">
    <form action="{{route('admin.pos.order')}}" id='order_place' method="post">
        @csrf

        <div class="bg-color4 rounded p-10px">
            <div class="pt-1 mb-0">
                <div class="text-title d-flex mb-3">{{translate('Paid_By')}} :</div>
                <ul class="list-unstyled option-buttons">
                    <li id="cash_payment_li" style="display: {{ session('order_type') != 'home_delivery' ?  'block' : 'none' }}">
                        <input type="radio" class="paid-by" id="cash" value="cash" name="type" hidden="" {{ session('order_type') != 'home_delivery' ?  'checked' : '' }}>
                        <label for="cash" class="btn btn-bordered px-4 mb-0">{{translate('Cash')}}</label>
                    </li>
                    <li id="card_payment_li" style="display: {{ session('order_type') != 'home_delivery' ?  'block' : 'none' }}">
                        <input type="radio" class="paid-by" value="card" id="card" name="type" hidden="">
                        <label for="card" class="btn btn-bordered px-4 mb-0">{{translate('Card')}}</label>
                    </li>
                    <li id="pay_after_eating_li" style="display: {{ session('order_type') == 'dine_in' ?  'block' : 'none' }}">
                        <input type="radio" class="paid-by" value="pay_after_eating" id="pay_after_eating" name="type" hidden="">
                        <label for="pay_after_eating" class="btn btn-bordered px-4 mb-0">{{translate('pay_after_eating')}}</label>
                    </li>
                    <li id="cash_on_delivery_li" style="display: {{ session('order_type') == 'home_delivery' ?  'block' : 'none' }}">
                        <input type="radio" class="paid-by" value="cash_on_delivery" id="cash_on_delivery" name="type" hidden="" {{ session('order_type') == 'home_delivery' ?  'checked' : '' }}>
                        <label for="cash_on_delivery" class="btn btn-bordered px-4 mb-0">{{translate('cash_on_delivery')}}</label>
                    </li>
                    <li id="wallet_payment_li" class="d-none">
                        <input type="radio" class="paid-by" value="wallet_payment" id="wallet_payment" name="type" hidden="">
                        <label for="wallet_payment" class="btn btn-bordered px-4 mb-0">{{translate('wallet')}}</label>
                    </li>
                </ul>
            </div>

            <div class="card p-3 mb-20 mt-20 customer-wallet-info-card d-none">
                <div>
                    <p class="">{{ translate('available balance') }} : <span class="font-weight-bold available-wallet-balance"></span> <span class="text-danger used-wallet-amount"></span> </p>
                </div>
            </div>

            <div class="wallet-remaining-card mt-2 d-none">

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="balance-label text-dark">{{ translate('Paid Amount') }} :</span>
                    <span class="paid-by-wallet-amount rounded p-2"></span>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="balance-label text-dark">{{ translate('Remaining Balance') }} :</span>
                    <span class="remaining-order-amount"></span>
                </div>

                <div class="text-dark d-flex mb-2">{{ translate('Pay Remaining Balance By') }}</div>

                <div class="form-group">
                    <ul class="list-unstyled option-buttons">
                        <li id="wallet_with_cash_li">
                            <input type="radio" class="wallet-remaining-paid-by" value="cash" id="wallet-cash" name="wallet_partial_payment_method" hidden="">
                            <label for="wallet-cash" class="btn btn-bordered px-4 mb-0">{{translate('Cash')}}</label>
                        </li>
                        <li id="wallet_with_card_li">
                            <input type="radio" class="wallet-remaining-paid-by" value="card" id="wallet-card" name="wallet_partial_payment_method" hidden="">
                            <label for="wallet-card" class="btn btn-bordered px-4 mb-0">{{translate('Card')}}</label>
                        </li>
                    </ul>

                </div>

            </div>

            <div class="collect-cash-section mt-20" style="display: {{ session('order_type') != 'home_delivery' ?  'block' : 'none' }}">
                <div class="form-group mb-2 d-flex align-items-center justify-content-between gap-2">
                    <label class="w-50 mb-0">{{ translate('Paid Amount') }} :</label>
                    <input type="number" class="form-control w-50 text-right" name="paid_amount" step="0.01" id="paid-amount" value="{{ round($totalOrderAmount, 2) }}" onkeyup="calculateAmountDifference()" required>
                    <input type="hidden" class="hidden-paid-amount" value="{{ round($totalOrderAmount, 2) }}">
                    <input type="hidden" id="hiddenProductAndAddonPriceAfterDiscount" value="{{ round($totalProductAndAddonPriceAfterDiscount, 2) }}">
                </div>
                <div class="form-group d-flex align-items-center justify-content-between gap-2">
                    <label class="due-or-change-amount w-50 mb-0">{{ translate('Change Amount') }} :</label>
                    <input type="number" class="form-control text-right w-50 border-0 shadow-none" id="amount-difference" value="0" step="0.01" readonly required>
                </div>
            </div>
        </div>

        <div class="pos-order-btn">
            <div class="row gy-2 gx-2">
                <div class="col-6">
                    <a href="#" class="btn btn-secondary btn-block empty-cart-button">
                        <i class="fa fa-times-circle"></i> {{translate('Cancel_Order')}}
                    </a>
                </div>
                <div class="col-6">
                    <button type="submit" class="btn btn-primary btn-block order-place-btn">
                        <i class="fa fa-shopping-bag"></i>
                        {{translate('Place_Order')}}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>


    <div class="modal fade" id="add-discount" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{translate('update_discount')}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{route('admin.pos.discount')}}" method="post" class="row mb-0">
                        @csrf
                        <div class="form-group col-sm-6">
                            <label class="text-dark">{{translate('discount')}}</label>
                            <input type="number" class="form-control" name="discount" value="{{ $extraDiscountType == 'amount' ? $extraDiscountAmount : $extraDiscount }}" min="0" step="0.1">
                        </div>
                        <div class="form-group col-sm-6">
                            <label class="text-dark">{{translate('type')}}</label>
                            <select name="type" class="form-control">
                                <option value="amount" {{$extraDiscountType=='amount'?'selected':''}}>{{translate('amount')}}
                                    ({{\App\CentralLogics\Helpers::currency_symbol()}})
                                </option>
                                <option value="percent" {{$extraDiscountType=='percent'?'selected':''}}>{{translate('percent')}}
                                    (%)
                                </option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end col-sm-12">
                            <button class="btn btn-sm btn-primary" type="submit">{{translate('submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="add-tax" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{translate('update_tax')}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{route('admin.pos.tax')}}" method="POST" class="row">
                        @csrf
                        <div class="form-group col-12">
                            <label for="">{{translate('tax')}} (%)</label>
                            <input type="number" class="form-control" name="tax" min="0">
                        </div>

                        <div class="form-group col-sm-12">
                            <button class="btn btn-sm btn-primary" type="submit">{{translate('submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="add-coupon-discount" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered madl--lg">
            <div class="modal-content apply-coupon-modal">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fs-20">{{ translate('Coupon Discount') }}</h5>
                    <button type="button" class="close bg-color3 w-32px h-32px rounded-circle" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>

                <p class="px-4 text-muted small fs-14">{{ translate('Select from available coupon or input code') }}</p>

                <form action="{{route('admin.pos.coupon.apply')}}" method="post">
                    @csrf
                    <div class="modal-body pt-2 pb-3">
                        <div class="bg-color4 rounded p-20">
                            <h4 class="px-1 mb-15">{{ translate('Select Coupons') }}</h4>
                            <div class="coupon-slide-wrap position-relative">

                                <div id="dynamic-coupon-list" class="coupon-list coupon-inner pt-2 d-flex align-items-center flex-nowrap text-nowrap">

                                </div>

                                <div class="arrow-area">
                                    <div class="button-prev align-items-center">
                                        <button type="button" class="btn btn-click-prev mr-auto btn-primary w-25px h-25px min-w-25px rounded-circle fs-12 p-2 d-center">
                                            <i class="tio-arrow-backward top-02"></i>
                                        </button>
                                    </div>
                                    <div class="button-next align-items-center">
                                        <button type="button" class="btn btn-click-next ml-auto btn-primary w-25px h-25px min-w-25px rounded-circle fs-12 p-2 d-center">
                                            <i class="tio-arrow-forward top-02"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- OR enter coupon -->
                            <div class="text-center mb-4 text-c2 mt-3">
                                {{ translate('Or Enter A Coupon Code') }}
                            </div>
                            <!-- Manual Input -->
                            <div>
                                <label class="fs-14 mb-15 font-weight-normal">{{ translate('Coupon Code') }}</label>
                                <input type="text" id="couponInput" name="coupon_code" class="form-control"
                                    value="{{ session()->get('cart')['coupon_code'] ?? '' }}"
                                    placeholder="Enter coupon code" required maxlength="255">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0">
                        <button class="btn btn-light min-w-120px" data-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button class="btn btn-primary px-4 min-w-120px">{{ translate('Apply') }}</button>
                    </div>
                </form>


            </div>
        </div>
    </div>

    <div class="modal fade" id="confirm-remove-coupon" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fs-18">{{ translate('Remove Coupon') }}</h5>
                    <button type="button" class="close bg-color3 w-32px h-32px rounded-circle" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body text-center">
                    <img src="{{ asset('public/assets/admin/svg/components/info.svg') }}" alt="">
                    <p class="my-3">{{ translate('Are you sure you want to remove the applied coupon') }}?</p>
                </div>

                <div class="modal-footer border-0 d-flex justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="button" class="btn btn-danger px-4" id="confirm-remove-btn">{{ translate('Remove') }}</button>
                </div>

            </div>
        </div>
    </div>


    <script>
        "use strict";

        function calculateAmountDifference() {
            let paidAmountStr = $('#paid-amount').val().replace(/[^0-9.]/g, '');
            let paidAmount = parseFloat(paidAmountStr) || 0;
            let orderAmount = {{ $totalOrderAmount }};
            let difference = paidAmount - orderAmount;

            let label = $('.due-or-change-amount');
            let differenceInput = $('#amount-difference');
            let placeOrderButton = $('.order-place-btn');

            if (paidAmount >= orderAmount) {
                label.text('Change Amount :');
                differenceInput.val(difference.toFixed(2));
                placeOrderButton.prop('disabled', false); // Enable button
            } else {
                label.text('Due Amount :');
                differenceInput.val(difference.toFixed(2));
                placeOrderButton.prop('disabled', true); // Disable button
            }
        }

        // Update paid-by radio button handler
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
                let usedWalletAmount = walletAmount > totalOrderAmount ? totalOrderAmount : walletAmount

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
                // Reset paid amount to order amount
                $('#paid-amount').val(totalOrderAmount);
                calculateAmountDifference();
            } else {
                $('#paid-amount').removeAttr('readonly');
                $('#paid-amount').removeClass('bg-F5F5F5');
            }
        });

        // Initialize on page load
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

        })


    </script>
