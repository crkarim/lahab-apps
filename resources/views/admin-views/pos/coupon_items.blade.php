@forelse($coupons as $coupon)
    <label class="coupon-card coupon-slide_items position-relative" data-code="{{ $coupon->code }}">
        <div class="check-badge position-absolute">
            <i class="tio-checkmark-circle text-primary fs-16"></i>
        </div>

        <input type="radio" name="selected_coupon" hidden>

        <div class="coupon-body d-flex align-items-center gap-1 justify-content-between">
            <div class="w-60">
                <span class="code">{{ translate('Code') }}: {{ $coupon->code }}</span>
                <span class="desc">{{ $coupon->title ?? 'Use this coupon' }}</span>
            </div>

            <div class="line"></div>

            <span class="discount big text-center d-flex flex-column gap-0 align-items-center fs-16">
            @if($coupon->discount_type == 'percent')
                    {{ $coupon->discount }}%
                @else
                    {{ Helpers::set_symbol($coupon->discount) }}
                @endif
                <small class="text-danger d-block fs-10 font-weight-semibold lh-1">{{ translate('Discount') }}</small>
            </span>
        </div>
    </label>
@empty
    <div class="text-center">
        <span class="text-muted px-2">{{ translate('No coupons available') }}</span>
    </div>
@endforelse
