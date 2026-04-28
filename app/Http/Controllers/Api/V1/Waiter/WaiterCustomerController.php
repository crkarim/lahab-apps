<?php

namespace App\Http\Controllers\Api\V1\Waiter;

use App\Http\Controllers\Controller;
use App\Model\Order;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Customer-first capture for the waiter POS — same shape and semantics
 * as the admin-panel `POSController::customer_lookup` / `quick_add_customer`,
 * exposed as a token-protected JSON API.
 *
 * Phone normalisation mirrors the admin POS exactly so a customer
 * captured from the tablet matches the same `User.phone` row a counter
 * operator would have created.
 */
class WaiterCustomerController extends Controller
{
    /** Phone in any format the operator types → canonical +880XXXXXXXXXX or null. */
    private function normaliseBdPhone(?string $raw): ?string
    {
        if (!$raw) return null;
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($digits, '880')) $digits = substr($digits, 3);
        if (str_starts_with($digits, '0'))   $digits = substr($digits, 1);
        if (strlen($digits) !== 10) return null;
        return '+880' . $digits;
    }

    /** GET / POST — `{ phone: '01711...' }`. Returns `{ found, ... }`. */
    public function lookup(Request $request): JsonResponse
    {
        $phone = $this->normaliseBdPhone($request->input('phone'));
        if (!$phone) {
            return response()->json([
                'found'   => false,
                'invalid' => true,
                'message' => translate('Phone must be 10 digits (no leading 0) or 11 digits (with leading 0).'),
            ]);
        }

        $user = User::where('phone', $phone)
            ->select('id', 'f_name', 'l_name', 'phone', 'point', 'wallet_balance')
            ->first();

        if (!$user) {
            return response()->json([
                'found' => false,
                'phone' => $phone,
            ]);
        }

        $orderCount = Order::where('user_id', $user->id)->count();
        $lastOrder  = Order::where('user_id', $user->id)->latest('created_at')->value('created_at');

        return response()->json([
            'found'           => true,
            'id'              => $user->id,
            'name'            => trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')),
            'phone'           => $user->phone,
            'loyalty_points'  => (float) $user->point,
            'wallet_balance'  => (float) $user->wallet_balance,
            'order_count'     => $orderCount,
            'last_order_at'   => $lastOrder?->toDateTimeString(),
            'last_order_human'=> $lastOrder ? $lastOrder->diffForHumans() : null,
        ]);
    }

    /**
     * POST — `{ id, name }`. Set or correct the display name on an
     * existing customer. Lets the waiter clean up "Customer" placeholders
     * when the customer eventually gives their name, without making them
     * walk to the admin panel.
     */
    public function updateName(Request $request): JsonResponse
    {
        $request->validate([
            'id'   => 'required|integer|exists:users,id',
            'name' => 'required|string|max:100',
        ]);

        $user = User::find($request->id);
        $name = trim((string) $request->input('name'));
        $parts = explode(' ', $name, 2);
        $user->f_name = $parts[0];
        $user->l_name = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null;
        $user->save();

        return response()->json([
            'success' => true,
            'id'      => $user->id,
            'name'    => trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')),
        ]);
    }

    /** POST — `{ phone, name? }`. Creates a User row with a placeholder password. */
    public function quickAdd(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required',
            'name'  => 'nullable|string|max:100',
        ]);

        $phone = $this->normaliseBdPhone($request->input('phone'));
        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => translate('Phone must be 10 digits (no leading 0) or 11 digits (with leading 0).'),
            ], 422);
        }

        if (User::where('phone', $phone)->exists()) {
            return response()->json([
                'success' => false,
                'message' => translate('A customer with this phone already exists.'),
            ], 409);
        }

        $name = trim((string) $request->input('name'));

        $user = User::create([
            'f_name'   => $name !== '' ? $name : 'Customer',
            'l_name'   => null,
            'email'    => null,
            'phone'    => $phone,
            'password' => bcrypt(Str::random(20)),
        ]);

        return response()->json([
            'success' => true,
            'id'      => $user->id,
            'name'    => $user->f_name,
            'phone'   => $user->phone,
        ]);
    }
}
