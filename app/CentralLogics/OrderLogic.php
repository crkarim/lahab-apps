<?php

namespace App\CentralLogics;

use App\Model\CustomerAddress;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\Product;
use App\Model\OrderTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderLogic
{
    public static function track_order($order_id)
    {
        $order = Order::with(['details',
            'delivery_man' => function ($query) {
                $query->withCount('reviews'); // Count reviews
            },
            'delivery_man.rating','order_partial_payments', 'branch', 'offline_payment', 'order_change_amount'])
            ->where(['id' => $order_id])
            ->first();

        $orderDetails = OrderDetail::where('order_id', $order->id)->first();
        $productId = $orderDetails?->product_id;
        $order['is_product_available'] = $productId ? Product::find($productId) ? 1 : 0 : 0;

        $order->offline_payment_information = $order->offline_payment ? json_decode($order->offline_payment->payment_info, true): null;
        $order->delivery_address = $order->delivery_address ?? CustomerAddress::find($order->delivery_address_id);

        return Helpers::order_data_formatting($order, false);
    }

    public static function create_transaction($order, $received_by=false)
    {
        try{
            $order_transaction = new OrderTransaction;
            $order_transaction->delivery_man_id = $order->delivery_man_id;
            $order_transaction->order_id = $order->id;
            $order_transaction->order_amount = $order->order_amount;
            $order_transaction->delivery_charge = $order->delivery_charge;
            $order_transaction->original_delivery_charge = $order->delivery_charge;
            $order_transaction->tax = $order->total_tax_amount;
            $order_transaction->received_by = $received_by?$received_by:'admin';
            $order_transaction->save();

        } catch(\Exception $e) {
            DB::rollBack();
            info($e);
            return false;
        }
        return true;
    }
}
