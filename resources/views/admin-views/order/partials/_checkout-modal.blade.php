{{-- Checkout modal for in-restaurant orders.
     Expects: $order (with ->order_amount, ->customer), $offline_methods (collection of OfflinePaymentMethod).
     Triggered by: <button data-toggle="modal" data-target="#checkout-modal"> --}}

<div class="modal fade lh-checkout" id="checkout-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content lh-co-content">
            <form method="POST" action="{{ route('admin.orders.checkout.submit', ['id' => $order->id]) }}" id="checkout-form">
                @csrf

                {{-- Header — title + paid badge if applicable --}}
                <div class="modal-header lh-co-header">
                    <div>
                        <div class="lh-co-eyebrow">{{ translate('Order') }} #{{ $order->id }}</div>
                        <h5 class="modal-title mb-0">
                            @if($order->payment_status === 'paid')
                                {{ translate('Receipt') }}
                            @else
                                {{ translate('Checkout') }}
                            @endif
                        </h5>
                    </div>
                    <div class="ml-auto d-flex align-items-center gap-2">
                        @if($order->payment_status === 'paid')
                            <span class="badge badge-success lh-co-paid-badge">
                                <i class="tio-checkmark-circle"></i> {{ translate('PAID') }}
                            </span>
                        @endif
                        <button type="button" class="close lh-co-close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>

                <div class="modal-body lh-co-body">
                    {{-- Two columns on md+ — form on the left, sticky summary
                         card on the right. Stacks vertically below 768px.
                         No body scroll; content sized to fit ~85vh. --}}
                    <div class="lh-co-grid">

                        {{-- LEFT COLUMN — adjustments, payments, receipt --}}
                        <div class="lh-co-col-form">

                            <div class="lh-co-section lh-co-adjustments" @if($order->payment_status === 'paid') style="display:none;" @endif>
                                <div class="lh-co-section-title">
                                    <i class="tio-edit"></i> {{ translate('Adjustments') }}
                                </div>
                                <div class="row no-gutters lh-co-row-tight">
                                    <div class="col-4 pr-2">
                                        <label class="lh-co-input-label" for="co-tip">{{ translate('Tip') }}</label>
                                        <input type="number" step="0.01" min="0" name="tip_amount" id="co-tip"
                                               class="form-control lh-co-input" value="0" placeholder="0">
                                    </div>
                                    <div class="col-4 pr-2">
                                        <label class="lh-co-input-label" for="co-discount">{{ translate('Discount') }}</label>
                                        <input type="number" step="0.01" min="0" name="discount_amount" id="co-discount"
                                               class="form-control lh-co-input" value="0" placeholder="0">
                                    </div>
                                    <div class="col-4">
                                        <label class="lh-co-input-label" for="co-discount-reason">{{ translate('Reason') }}</label>
                                        <input type="text" name="discount_reason" id="co-discount-reason"
                                               class="form-control lh-co-input" placeholder="{{ translate('e.g. Staff meal') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="lh-co-section lh-co-payments-section" @if($order->payment_status === 'paid') style="display:none;" @endif>
                                <div class="lh-co-section-title">
                                    <i class="tio-credit-card"></i> {{ translate('Payment') }}
                                </div>

                                <div id="co-payments">
                                    <div class="lh-co-payment-row co-payment-row">
                                        <div class="lh-co-method-wrap">
                                            <select name="payments[0][method]" class="form-control lh-co-method co-method">
                                                <option value="cash">💵 {{ translate('Cash') }}</option>
                                                <option value="card">💳 {{ translate('Card') }}</option>
                                                @foreach($offline_methods ?? [] as $m)
                                                    <option value="offline:{{ $m->id }}">📱 {{ $m->method_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="lh-co-amount-wrap">
                                            <input type="number" step="0.01" min="0" name="payments[0][amount]"
                                                   class="form-control lh-co-amount co-amount"
                                                   placeholder="{{ translate('Amount') }}"
                                                   value="{{ number_format($order->order_amount, 2, '.', '') }}">
                                        </div>
                                        <div class="lh-co-row-actions">
                                            <button type="button" class="btn lh-co-row-btn co-add-row" title="{{ translate('Split') }}">
                                                <i class="tio-add"></i>
                                            </button>
                                            <button type="button" class="btn lh-co-row-btn lh-co-row-btn-danger co-remove-row" title="{{ translate('Remove') }}" style="display:none;">
                                                <i class="tio-remove"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="lh-co-quick-cash">
                                    <button type="button" class="btn lh-co-quick-btn" data-quick-cash="exact">{{ translate('Exact') }}</button>
                                    <button type="button" class="btn lh-co-quick-btn" data-quick-cash="500">৳ 500</button>
                                    <button type="button" class="btn lh-co-quick-btn" data-quick-cash="1000">৳ 1,000</button>
                                    <button type="button" class="btn lh-co-quick-btn" data-quick-cash="2000">৳ 2,000</button>
                                </div>
                            </div>

                            <div class="lh-co-section">
                                <div class="lh-co-section-title">
                                    <i class="tio-receipt"></i> {{ translate('Receipt') }}
                                </div>
                                {{-- No Bootstrap data-toggle="buttons" here — that
                                     wrapper expects .btn children and hijacks click
                                     events in a way that prevented our chips from
                                     toggling. Standard label-wraps-input radio
                                     behaviour drives the selection; our JS adds
                                     the .active class. --}}
                                <div class="lh-co-receipt-group">
                                    <label class="lh-co-receipt-chip {{ $order->payment_status === 'paid' ? '' : 'active' }}">
                                        <input type="radio" name="receipt_delivery" value="print" {{ $order->payment_status === 'paid' ? '' : 'checked' }}>
                                        <i class="tio-print"></i> <span>{{ translate('Print') }}</span>
                                    </label>
                                    <label class="lh-co-receipt-chip {{ $order->payment_status === 'paid' ? 'active' : '' }}">
                                        <input type="radio" name="receipt_delivery" value="sms" {{ $order->payment_status === 'paid' ? 'checked' : '' }}>
                                        <i class="tio-sms"></i> <span>{{ translate('SMS') }}</span>
                                    </label>
                                    <label class="lh-co-receipt-chip">
                                        <input type="radio" name="receipt_delivery" value="both">
                                        <i class="tio-layers"></i> <span>{{ translate('Both') }}</span>
                                    </label>
                                    <label class="lh-co-receipt-chip lh-co-receipt-chip-quiet">
                                        <input type="radio" name="receipt_delivery" value="none">
                                        <i class="tio-blocked"></i> <span>{{ translate('None') }}</span>
                                    </label>
                                </div>

                                <div id="co-phone-wrap" class="form-group mt-2 mb-0"
                                     @if(!in_array($order->payment_status === 'paid' ? 'sms' : '', ['sms','both'])) style="display:none;" @endif>
                                    <input type="tel" name="receipt_phone" id="co-phone" class="form-control lh-co-input"
                                           value="{{ $order->customer?->phone ?? '' }}"
                                           placeholder="+8801XXXXXXXXX — {{ translate('SMS receipt link') }}">
                                </div>
                            </div>
                        </div>

                        {{-- RIGHT COLUMN — sticky summary card with hero + status --}}
                        <aside class="lh-co-col-summary">
                            <div class="lh-co-summary-card">
                                <div class="lh-co-summary-label">{{ translate('Total to collect') }}</div>
                                <div class="lh-co-summary-amount" id="co-grand-total">{{ Helpers::set_symbol($order->order_amount) }}</div>

                                <div class="lh-co-summary-rows">
                                    <div class="lh-co-summary-row">
                                        <span>{{ translate('Subtotal') }}</span>
                                        <strong id="co-subtotal" data-value="{{ $order->order_amount }}">{{ Helpers::set_symbol($order->order_amount) }}</strong>
                                    </div>
                                    <div class="lh-co-summary-row">
                                        <span>{{ translate('Tip') }}</span>
                                        <strong id="co-tip-display">{{ Helpers::set_symbol(0) }}</strong>
                                    </div>
                                    <div class="lh-co-summary-row">
                                        <span>{{ translate('Discount') }}</span>
                                        <strong id="co-discount-display">−{{ Helpers::set_symbol(0) }}</strong>
                                    </div>
                                </div>

                                {{-- Live paid / change indicator inside summary card --}}
                                <div id="co-status-block" class="lh-co-status lh-co-status-success">
                                    <div class="lh-co-status-row">
                                        <span class="lh-co-status-label">{{ translate('Paid') }}</span>
                                        <span class="lh-co-status-value" id="co-total-paid">{{ Helpers::set_symbol($order->order_amount) }}</span>
                                    </div>
                                    <div class="lh-co-status-row" id="co-change-block">
                                        <span class="lh-co-status-label">{{ translate('Change') }}</span>
                                        <span class="lh-co-status-value" id="co-change">{{ Helpers::set_symbol(0) }}</span>
                                    </div>
                                </div>

                                @if($order->payment_status === 'paid')
                                    <div class="lh-co-summary-paid-mark">
                                        <i class="tio-checkmark-circle"></i> {{ translate('PAID') }}
                                    </div>
                                @endif
                            </div>
                        </aside>
                    </div>
                </div>

                {{-- Sticky footer — primary action dominates --}}
                <div class="modal-footer lh-co-footer">
                    <button type="button" class="btn btn-light lh-co-btn-cancel" data-dismiss="modal">
                        {{ translate('Cancel') }}
                    </button>
                    <button type="submit" class="btn lh-co-btn-confirm">
                        <i class="tio-checkmark-circle"></i>
                        {{ $order->payment_status === 'paid' ? translate('Send Receipt') : translate('Confirm Payment') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ─── Lahab checkout modal — single-page POS layout ───────────────────── */
.lh-checkout .lh-co-content {
    border-radius: 14px; border: none; overflow: hidden;
    box-shadow: 0 16px 48px rgba(0,0,0,0.18);
}

/* HEADER — slim */
.lh-checkout .lh-co-header {
    background: linear-gradient(135deg, #FFF8F0 0%, #fff 100%);
    border-bottom: 1px solid #f4e6d5;
    padding: 12px 18px; align-items: center;
}
.lh-checkout .lh-co-eyebrow {
    font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
    text-transform: uppercase; color: #E67E22;
}
.lh-checkout .modal-title { color: #2d2d33; font-weight: 700; font-size: 17px; }
.lh-checkout .lh-co-paid-badge {
    background: #28a745 !important; color: #fff !important;
    font-size: 10px; padding: 3px 8px; border-radius: 5px;
    letter-spacing: 1.5px;
}
.lh-checkout .lh-co-close {
    width: 28px; height: 28px; border-radius: 50%;
    background: #f2f2f7; border: none; opacity: 1; font-size: 18px;
    display: flex; align-items: center; justify-content: center;
}
.lh-checkout .lh-co-close:hover { background: #e5e5ea; }

/* BODY — two-column grid, no scroll */
.lh-checkout .lh-co-body { padding: 0; background: #f7f7fa; }
.lh-checkout .lh-co-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 0;
    align-items: stretch;
}
.lh-checkout .lh-co-col-form { padding: 0; background: #fff; }
.lh-checkout .lh-co-col-summary { background: #f7f7fa; padding: 14px; border-left: 1px solid #ececf0; }

/* SECTIONS — tight */
.lh-checkout .lh-co-section {
    padding: 12px 18px; border-bottom: 1px solid #ececf0;
}
.lh-checkout .lh-co-section:last-child { border-bottom: none; }
.lh-checkout .lh-co-section-title {
    font-size: 11px; font-weight: 700; color: #6B2F1A;
    text-transform: uppercase; letter-spacing: 1px;
    margin-bottom: 8px; display: flex; align-items: center;
}
.lh-checkout .lh-co-section-title i { font-size: 14px; margin-right: 6px; color: #E67E22; }
.lh-checkout .lh-co-input-label {
    font-size: 10px; font-weight: 600; color: #6a6a70;
    text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;
    display: block;
}
.lh-checkout .lh-co-input {
    height: 38px; font-size: 14px; border-radius: 7px;
    border: 1.5px solid #e5e5ea; background: #fff; padding: 0 10px;
    transition: border-color 120ms, box-shadow 120ms;
}
.lh-checkout .lh-co-input:focus {
    border-color: #E67E22 !important;
    box-shadow: 0 0 0 3px rgba(230,126,34,0.12) !important;
}

/* PAYMENTS — slimmer */
.lh-checkout .lh-co-payment-row {
    display: flex; gap: 6px; align-items: stretch; margin-bottom: 8px;
}
.lh-checkout .lh-co-method-wrap { flex: 1 1 50%; }
.lh-checkout .lh-co-amount-wrap { flex: 1 1 40%; }
.lh-checkout .lh-co-row-actions { flex: 0 0 auto; display: flex; gap: 4px; }
.lh-checkout .lh-co-method, .lh-checkout .lh-co-amount {
    height: 42px; font-size: 14px; font-weight: 600;
    border-radius: 7px; border: 1.5px solid #e5e5ea;
}
.lh-checkout .lh-co-method:focus, .lh-checkout .lh-co-amount:focus {
    border-color: #E67E22 !important;
    box-shadow: 0 0 0 3px rgba(230,126,34,0.12) !important;
}
.lh-checkout .lh-co-row-btn {
    width: 38px; height: 42px; border-radius: 7px; padding: 0;
    background: #f2f2f7; color: #2d2d33; border: 1.5px solid #e5e5ea;
    display: flex; align-items: center; justify-content: center;
}
.lh-checkout .lh-co-row-btn:hover { background: #e5e5ea; }
.lh-checkout .lh-co-row-btn-danger {
    background: #fff0f0; color: #c83030; border-color: #f5cfcf;
}

/* QUICK CASH — inline chips, no extra padding wrapper */
.lh-checkout .lh-co-quick-cash {
    display: flex; flex-wrap: wrap; gap: 6px;
}
.lh-checkout .lh-co-quick-btn {
    background: #fff; border: 1.5px solid #e5e5ea;
    color: #2d2d33; font-weight: 600; font-size: 12px;
    padding: 5px 12px; border-radius: 6px; flex: 1;
}
.lh-checkout .lh-co-quick-btn:hover {
    background: #FFF3E6; border-color: #E67E22; color: #6B2F1A;
}

/* RECEIPT CHIPS — compact */
.lh-checkout .lh-co-receipt-group {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
}
.lh-checkout .lh-co-receipt-chip {
    cursor: pointer; padding: 8px 4px; border-radius: 8px;
    background: #f7f7fa; border: 2px solid transparent;
    text-align: center; font-weight: 600; font-size: 12px;
    color: #2d2d33; margin: 0; display: flex; flex-direction: column;
    align-items: center; gap: 3px;
    transition: background 120ms, border-color 120ms, color 120ms;
}
.lh-checkout .lh-co-receipt-chip i { font-size: 18px; color: #6a6a70; }
/* Hide the radio without display:none so the label still wires up the
   click → check natively. display:none breaks click-through in some
   browsers; a zero-size absolute input doesn't. */
.lh-checkout .lh-co-receipt-chip input {
    position: absolute; opacity: 0; width: 0; height: 0;
    pointer-events: none; margin: 0;
}
.lh-checkout .lh-co-receipt-chip:hover {
    background: #FFF3E6; color: #6B2F1A;
}
.lh-checkout .lh-co-receipt-chip:hover i { color: #E67E22; }
.lh-checkout .lh-co-receipt-chip.active {
    background: #FFF3E6; border-color: #E67E22; color: #6B2F1A;
}
.lh-checkout .lh-co-receipt-chip.active i { color: #E67E22; }
.lh-checkout .lh-co-receipt-chip-quiet { opacity: 0.7; }

/* SUMMARY CARD — sticky right column */
.lh-checkout .lh-co-summary-card {
    background: linear-gradient(160deg, #E67E22 0%, #C9661A 100%);
    color: #fff; border-radius: 12px; padding: 18px 16px;
    box-shadow: 0 8px 24px rgba(230,126,34,0.25);
}
.lh-checkout .lh-co-summary-label {
    font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
    text-transform: uppercase; opacity: 0.85; margin-bottom: 4px;
    text-align: center;
}
.lh-checkout .lh-co-summary-amount {
    font-size: 32px; font-weight: 800; line-height: 1.05;
    text-align: center; font-variant-numeric: tabular-nums;
    margin-bottom: 14px;
}
.lh-checkout .lh-co-summary-rows {
    background: rgba(255,255,255,0.12); border-radius: 8px;
    padding: 8px 12px; margin-bottom: 10px;
}
.lh-checkout .lh-co-summary-row {
    display: flex; justify-content: space-between;
    font-size: 12px; padding: 2px 0;
}
.lh-checkout .lh-co-summary-row strong {
    font-weight: 700; font-variant-numeric: tabular-nums;
}
.lh-checkout .lh-co-summary-paid-mark {
    margin-top: 10px; padding: 6px; text-align: center;
    background: #28a745; color: #fff; border-radius: 6px;
    font-weight: 700; font-size: 12px; letter-spacing: 1.5px;
}
.lh-checkout .lh-co-summary-paid-mark i { font-size: 14px; margin-right: 4px; }

/* PAID/DUE STATUS — INSIDE the summary card */
.lh-checkout .lh-co-status {
    background: rgba(255,255,255,0.95); border-radius: 8px;
    padding: 10px 12px; border: 2px solid;
}
.lh-checkout .lh-co-status-success { border-color: #c8edd6; color: #1a6b3a; }
.lh-checkout .lh-co-status-warning { border-color: #f5d99a; color: #8a5a00; }
.lh-checkout .lh-co-status-danger  { border-color: #f5b9b9; color: #b22020; }
.lh-checkout .lh-co-status-row {
    display: flex; justify-content: space-between; align-items: baseline;
}
.lh-checkout .lh-co-status-row + .lh-co-status-row { margin-top: 4px; }
.lh-checkout .lh-co-status-label {
    font-size: 11px; font-weight: 600; opacity: 0.85;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.lh-checkout .lh-co-status-value {
    font-size: 16px; font-weight: 800; font-variant-numeric: tabular-nums;
}

/* FOOTER — slim */
.lh-checkout .lh-co-footer {
    padding: 12px 18px; background: #fff;
    border-top: 1px solid #ececf0;
    display: flex; gap: 8px; justify-content: flex-end;
}
.lh-checkout .lh-co-btn-cancel {
    background: #f2f2f7; color: #2d2d33; border: none;
    padding: 10px 22px; font-weight: 600; border-radius: 8px;
    font-size: 14px;
}
.lh-checkout .lh-co-btn-cancel:hover { background: #e5e5ea; }
.lh-checkout .lh-co-btn-confirm {
    background: linear-gradient(135deg, #E67E22 0%, #C9661A 100%);
    color: #fff; border: none;
    padding: 10px 26px; font-weight: 700; border-radius: 8px;
    font-size: 14px; box-shadow: 0 4px 12px rgba(230,126,34,0.35);
    display: inline-flex; align-items: center; gap: 6px;
}
.lh-checkout .lh-co-btn-confirm:hover {
    background: linear-gradient(135deg, #C9661A 0%, #a64f0d 100%);
    box-shadow: 0 6px 16px rgba(230,126,34,0.45);
    transform: translateY(-1px);
}
.lh-checkout .lh-co-btn-confirm:active { transform: translateY(0); }
.lh-checkout .lh-co-btn-confirm i { font-size: 16px; }

/* RESPONSIVE — stack on small screens */
@media (max-width: 767px) {
    .lh-checkout .lh-co-grid { grid-template-columns: 1fr; }
    .lh-checkout .lh-co-col-summary { border-left: none; border-top: 1px solid #ececf0; }
    .lh-checkout .lh-co-summary-amount { font-size: 28px; }
}
</style>

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

        // Colour-code the status block: green when paid in full (with or
        // without change), amber when nothing entered yet, red when there
        // is an unpaid remainder.
        const $status = $('#co-status-block');
        $status.removeClass('lh-co-status-success lh-co-status-warning lh-co-status-danger');
        const $changeBlock = $('#co-change-block');
        if (due > 0.009) {
            $status.addClass('lh-co-status-danger');
            $changeBlock.html(
                '<span class="lh-co-status-label">{{ translate("Still due") }}</span>' +
                '<span class="lh-co-status-value">' + fmt(due) + '</span>'
            );
        } else if (totalPaid < 0.009) {
            $status.addClass('lh-co-status-warning');
            $changeBlock.html(
                '<span class="lh-co-status-label">{{ translate("Change due") }}</span>' +
                '<span class="lh-co-status-value">' + fmt(0) + '</span>'
            );
        } else {
            $status.addClass('lh-co-status-success');
            $changeBlock.html(
                '<span class="lh-co-status-label">{{ translate("Change due") }}</span>' +
                '<span class="lh-co-status-value">' + fmt(change) + '</span>'
            );
        }
    }

    $(document).on('input change', '#co-tip, #co-discount, .co-amount', recalc);

    // Quick-cash chips — fill the FIRST payment row's amount with a
    // common bill (or "exact" = grand total). Saves the operator from
    // typing the full amount when handed a 500 / 1000 / 2000 note.
    $(document).on('click', '.lh-co-quick-btn', function () {
        const v = $(this).data('quick-cash');
        const sub  = parseFloat($sub.data('value')) || 0;
        const tip  = parseFloat($('#co-tip').val()) || 0;
        const disc = parseFloat($('#co-discount').val()) || 0;
        const grand = Math.max(0, sub + tip - disc);
        const amount = (v === 'exact') ? grand : parseFloat(v);
        const $first = $('.co-amount').first();
        $first.val(amount.toFixed(2)).trigger('input');
    });

    // Receipt-chip selection — handle two events to cover both browsers
    // that fire `change` on the hidden radio when the label is clicked,
    // and any edge case where they don't. Click on the chip explicitly
    // sets the radio + active class + fires change so the SMS phone
    // field show/hide handler downstream still runs.
    $(document).on('click', '.lh-co-receipt-chip', function (e) {
        // If the click was directly on the input/label native flow
        // already handled it; we only need to update the visual state.
        const $chip  = $(this);
        const $group = $chip.closest('.lh-co-receipt-group');
        const $input = $chip.find('input[type=radio]');
        if (!$input.prop('checked')) {
            $input.prop('checked', true).trigger('change');
        }
        $group.find('.lh-co-receipt-chip').removeClass('active');
        $chip.addClass('active');
    });
    $(document).on('change', '.lh-co-receipt-group input[type=radio]', function () {
        $(this).closest('.lh-co-receipt-group').find('.lh-co-receipt-chip').removeClass('active');
        $(this).closest('.lh-co-receipt-chip').addClass('active');
    });

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
