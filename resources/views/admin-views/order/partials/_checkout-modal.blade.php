{{-- Checkout modal for in-restaurant orders.
     Expects: $order (with ->order_amount, ->customer), $offline_methods (collection of OfflinePaymentMethod).
     Triggered by: <button data-toggle="modal" data-target="#checkout-modal"> --}}

<div class="modal fade" id="checkout-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.orders.checkout.submit', ['id' => $order->id]) }}" id="checkout-form">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">
                        @if($order->payment_status === 'paid')
                            {{ translate('Receipt') }} — {{ translate('Order') }} #{{ $order->id }}
                        @else
                            {{ translate('Checkout') }} — {{ translate('Order') }} #{{ $order->id }}
                        @endif
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    @if($order->payment_status === 'paid')
                        <div class="alert alert-success">
                            <i class="tio-checkmark-circle"></i>
                            {{ translate('This order is already paid.') }}
                            {{ translate('You can re-send the receipt below.') }}
                        </div>
                    @endif

                    {{-- Totals summary --}}
                    <div class="bg-light rounded p-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>{{ translate('Subtotal') }}</span>
                            <strong id="co-subtotal" data-value="{{ $order->order_amount }}">{{ Helpers::set_symbol($order->order_amount) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span>{{ translate('Tip') }}</span>
                            <span id="co-tip-display">{{ Helpers::set_symbol(0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span>{{ translate('Discount') }}</span>
                            <span id="co-discount-display">−{{ Helpers::set_symbol(0) }}</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fz-18">
                            <span>{{ translate('Grand Total') }}</span>
                            <strong id="co-grand-total" class="text-primary">{{ Helpers::set_symbol($order->order_amount) }}</strong>
                        </div>
                    </div>

                    {{-- Tip + discount --}}
                    <div class="row mb-3" @if($order->payment_status === 'paid') style="display:none;" @endif>
                        <div class="col-md-4">
                            <label for="tip_amount">{{ translate('Tip') }}</label>
                            <input type="number" step="0.01" min="0" name="tip_amount" id="co-tip" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="discount_amount">{{ translate('Discount') }}</label>
                            <input type="number" step="0.01" min="0" name="discount_amount" id="co-discount" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="discount_reason">{{ translate('Reason') }}</label>
                            <input type="text" name="discount_reason" id="co-discount-reason" class="form-control" placeholder="{{ translate('e.g. Staff meal') }}">
                        </div>
                    </div>

                    {{-- Payment rows (split support) --}}
                    <div @if($order->payment_status === 'paid') style="display:none;" @endif>
                    <label class="mb-2">{{ translate('Payments') }}</label>
                    <div id="co-payments">
                        <div class="row co-payment-row align-items-end mb-2">
                            <div class="col-md-5">
                                <select name="payments[0][method]" class="form-control co-method">
                                    <option value="cash">{{ translate('Cash') }}</option>
                                    <option value="card">{{ translate('Card') }}</option>
                                    @foreach($offline_methods ?? [] as $m)
                                        <option value="offline:{{ $m->id }}">{{ $m->method_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" step="0.01" min="0" name="payments[0][amount]"
                                       class="form-control co-amount" placeholder="{{ translate('Amount') }}"
                                       value="{{ number_format($order->order_amount, 2, '.', '') }}">
                            </div>
                            <div class="col-md-3 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary co-add-row" title="{{ translate('Split') }}">
                                    <i class="tio-add"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger co-remove-row" title="{{ translate('Remove') }}" style="display:none;">
                                    <i class="tio-remove"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Cash calculation --}}
                    <div class="d-flex justify-content-between bg-success-soft p-2 rounded mb-3">
                        <span><strong>{{ translate('Total Paid') }}:</strong> <span id="co-total-paid">{{ Helpers::set_symbol($order->order_amount) }}</span></span>
                        <span id="co-change-block"><strong>{{ translate('Change Due') }}:</strong> <span id="co-change">{{ Helpers::set_symbol(0) }}</span></span>
                    </div>
                    </div>{{-- /payments section --}}

                    {{-- Receipt delivery --}}
                    <label class="mb-2">{{ translate('Receipt') }}</label>
                    <div class="form-group">
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                            <label class="btn btn-outline-primary {{ $order->payment_status === 'paid' ? '' : 'active' }}">
                                <input type="radio" name="receipt_delivery" value="print" {{ $order->payment_status === 'paid' ? '' : 'checked' }}> <i class="tio-print"></i> {{ translate('Print') }}
                            </label>
                            <label class="btn btn-outline-primary {{ $order->payment_status === 'paid' ? 'active' : '' }}">
                                <input type="radio" name="receipt_delivery" value="sms" {{ $order->payment_status === 'paid' ? 'checked' : '' }}> <i class="tio-sms"></i> {{ translate('SMS') }}
                            </label>
                            <label class="btn btn-outline-primary">
                                <input type="radio" name="receipt_delivery" value="both"> {{ translate('Both') }}
                            </label>
                            <label class="btn btn-outline-secondary">
                                <input type="radio" name="receipt_delivery" value="none"> {{ translate('None') }}
                            </label>
                        </div>
                    </div>

                    <div id="co-phone-wrap" class="form-group" @if(!in_array($order->payment_status === 'paid' ? 'sms' : '', ['sms','both'])) style="display:none;" @endif>
                        <label for="receipt_phone">{{ translate('Phone Number') }}</label>
                        <input type="tel" name="receipt_phone" id="co-phone" class="form-control"
                               value="{{ $order->customer?->phone ?? '' }}"
                               placeholder="+8801XXXXXXXXX">
                        <small class="form-text text-muted">{{ translate('Customer will receive an SMS with a link to view the receipt.') }}</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="tio-checkmark-circle"></i> {{ translate('Confirm Payment') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('script_2')
<script>
(function () {
    'use strict';

    const fmt   = v => {{ \Illuminate\Support\Js::from(Helpers::currency_symbol()) }} + Number(v || 0).toFixed(2);
    const $sub  = $('#co-subtotal');

    function recalc() {
        const sub      = parseFloat($sub.data('value')) || 0;
        const tip      = parseFloat($('#co-tip').val()) || 0;
        const disc     = parseFloat($('#co-discount').val()) || 0;
        const grand    = Math.max(0, sub + tip - disc);
        let totalPaid  = 0;
        $('.co-amount').each(function () { totalPaid += parseFloat($(this).val()) || 0; });
        const change   = Math.max(0, totalPaid - grand);
        const due      = Math.max(0, grand - totalPaid);

        $('#co-tip-display').text(fmt(tip));
        $('#co-discount-display').text('−' + fmt(disc));
        $('#co-grand-total').text(fmt(grand));
        $('#co-total-paid').text(fmt(totalPaid));
        $('#co-change').text(fmt(change));

        if (due > 0.009) {
            $('#co-change-block').html('<strong>{{ translate("Still Due") }}:</strong> <span class="text-danger">' + fmt(due) + '</span>');
        } else {
            $('#co-change-block').html('<strong>{{ translate("Change Due") }}:</strong> <span>' + fmt(change) + '</span>');
        }
    }

    $(document).on('input change', '#co-tip, #co-discount, .co-amount', recalc);

    let rowIdx = 0;
    $(document).on('click', '.co-add-row', function () {
        rowIdx += 1;
        const tmpl = $('#co-payments .co-payment-row').first().clone();
        tmpl.find('select').attr('name', 'payments[' + rowIdx + '][method]');
        tmpl.find('input').attr('name', 'payments[' + rowIdx + '][amount]').val('0');
        tmpl.find('.co-remove-row').show();
        tmpl.find('.co-add-row').hide();
        $('#co-payments').append(tmpl);
    });

    $(document).on('click', '.co-remove-row', function () {
        $(this).closest('.co-payment-row').remove();
        recalc();
    });

    $(document).on('change', 'input[name="receipt_delivery"]', function () {
        const v = $(this).val();
        $('#co-phone-wrap').toggle(v === 'sms' || v === 'both');
    });
})();
</script>
@endpush
