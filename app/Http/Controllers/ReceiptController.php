<?php

namespace App\Http\Controllers;

use App\Model\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReceiptController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('receipt.show', ['order' => $this->loadForToken($token)]);
    }

    /**
     * Admin/branch entry point: mint receipt_token if missing,
     * then redirect to /r/{token}?print=1 (auto-print in new tab).
     */
    public function printByOrderId($id): RedirectResponse
    {
        $order = $this->loadOrMintToken($id);
        return redirect()->route('receipt.show', ['token' => $order->receipt_token, 'print' => 1]);
    }

    /**
     * Returns the receipt body HTML fragment for an in-page modal preview.
     * Mints the receipt_token if missing.
     */
    public function fragment($id): JsonResponse
    {
        $order = $this->loadOrMintToken($id);
        $html  = view('receipt._fragment', compact('order'))->render();
        return response()->json(['html' => $html, 'token' => $order->receipt_token]);
    }

    private function loadOrMintToken($id): Order
    {
        $order = Order::findOrFail($id);
        if (empty($order->receipt_token)) {
            $order->receipt_token = Str::random(32);
            $order->save();
        }
        return $this->loadForToken($order->receipt_token);
    }

    private function loadForToken(string $token): Order
    {
        return Order::with(['details', 'customer', 'branch', 'table', 'order_partial_payments', 'placedBy.role'])
            ->where('receipt_token', $token)
            ->firstOrFail();
    }
}
