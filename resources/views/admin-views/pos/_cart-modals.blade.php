{{-- POS cart modals lifted out of _cart.blade.php to survive cart AJAX
     re-renders. When the cart partial was replaced via $('#cart').html(...),
     Bootstrap's modal state desynced — the old modal DOM disappeared but its
     .modal-backdrop / body.modal-open weren't cleaned up, trapping clicks
     and forcing a refresh to recover. Rendering these once at page level
     keeps the modal instance stable. --}}

<?php
    $sessionCart = session()->get('cart') ?? [];
    $extraDiscount = $sessionCart['extra_discount'] ?? 0;
    $extraDiscountType = $sessionCart['extra_discount_type'] ?? 'amount';
?>

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
                        <input type="number" class="form-control" name="discount" value="{{ $extraDiscount }}" min="0" step="0.1">
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
                        <div class="text-center mb-4 text-c2 mt-3">
                            {{ translate('Or Enter A Coupon Code') }}
                        </div>
                        <div>
                            <label class="fs-14 mb-15 font-weight-normal">{{ translate('Coupon Code') }}</label>
                            <input type="text" id="couponInput" name="coupon_code" class="form-control"
                                value="{{ $sessionCart['coupon_code'] ?? '' }}"
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
