<?php

use App\Model\Coupon;
use App\Model\Order;

if (!function_exists('applyPOSCoupon')){
    function applyPOSCoupon(string $code, float $amount, int|string|null $userId, int $isGuest): array
    {
        $coupon = Coupon::active()->where(['code' => $code])->first();

        if (!$coupon) {
            return [
                'coupon_discount_amount' => 0,
                'code' => null
            ];
        }

        if ($coupon['min_purchase'] && $amount < $coupon['min_purchase']) {
            return [
                'coupon_discount_amount' => 0,
                'code' => null,
            ];
        }

        // Check First Order Coupon
        if ($coupon['coupon_type'] == 'first_order') {
            if ($isGuest == 1) {
                return [
                    'coupon_discount_amount' => 0,
                    'code' => null
                ];
            }

            $totalOrders = Order::where(['user_id' => $userId, 'is_guest' => 0])->count();
            if ($totalOrders > 0) {
                return [
                    'coupon_discount_amount' => 0,
                    'code' => null
                ];
            }
        }

        // Usage Limit Check
        if ($coupon->limit !== null) {

            $query = Order::where('coupon_code', $code)->where('is_guest', $isGuest);

            if ($isGuest == 0) {
                $query->where('user_id', $userId);
            }

            $usageCount = $query->count();

            if ($usageCount >= $coupon->limit) {
                return ['coupon_discount_amount' => 0, 'code' => null];
            }
        }

        $discount = 0;

        if ($coupon['discount_type'] === 'percent') {
            $discount = ($amount * $coupon['discount']) / 100;

            if ($coupon['max_discount'] > 0 && $discount > $coupon['max_discount']) {
                $discount = $coupon['max_discount'];
            }
        } else { // fixed amount
            $discount = $coupon['discount'];
        }

        if ($discount > $amount) {
            $discount = $amount;
        }

        return [
            'coupon_discount_amount' => $discount,
            'code' => $coupon['code'],
        ];

    }

}

if (!function_exists('isPOSCouponAvailable')){
    function isPOSCouponAvailable(Coupon $coupon, float $amount, int|string|null $userId, int $isGuest): bool
    {
        // Min purchase check
        if ($coupon->min_purchase && $amount < $coupon->min_purchase) {
            return false;
        }

        // First order coupon
        if ($coupon->coupon_type === 'first_order') {

            if ($isGuest == 1) {
                return false;
            }

            $totalOrders = Order::where([
                'user_id' => $userId,
                'is_guest' => 0
            ])->count();

            if ($totalOrders > 0) {
                return false;
            }
        }

        // Usage limit check
        if ($coupon->limit !== null) {

            $query = Order::where('coupon_code', $coupon->code)
                ->where('is_guest', $isGuest);

            if ($isGuest == 0) {
                $query->where('user_id', $userId);
            }

            $usageCount = $query->count();

            if ($usageCount >= $coupon->limit) {
                return false;
            }
        }

        return true;
    }

}
