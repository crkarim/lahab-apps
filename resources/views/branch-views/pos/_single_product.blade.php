<div class="pos-product-item card quick-view-trigger" data-product-id="{{$product->id}}">
    <div class="pos-product-item_thumb d-center position-relative">
        <img class="img-fit" src="{{$product['imageFullPath']}}" alt="{{ translate('product') }}">
{{--        <div class="hover-add-cart position-absolute">--}}
{{--            <button class="btn p-0 bg-transparent font-weight-bolder fs-16 text-nowrap text-white text-add-to-cart" type="button">--}}
{{--                Add to cart--}}
{{--            </button>--}}
{{--        </div>--}}

{{--        <div class="total-cart-count">--}}
{{--            <div class="btn p-0 bg-white fs-14 font-weight-bolder text-dark w-35 h-35 rounded-circle mx-auto d-center count-product">--}}
{{--                10--}}
{{--            </div>--}}
{{--        </div>--}}
    </div>
    <?php
        $pb = json_decode($product->product_by_branch, true);
        $price = 0;
        $discountData = [];
        if(isset($pb[0])){
            $price = $pb[0]['price'];
            $discountData =[
                'discount_type' => $pb[0]['discount_type'],
                'discount' => $pb[0]['discount']
            ];
        }
    ?>

    <div class="pos-product-item_content clickable">
        <div class="pos-product-item_title">{{ Str::limit($product['name'], 15) }}</div>

        <div class="pos-product-item_price">
            {{ Helpers::set_symbol(($price - Helpers::discount_calculate($discountData, $price))) }}
        </div>
    </div>
</div>
