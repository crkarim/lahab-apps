<?php

namespace App\Http\Controllers\Api\V1\Waiter;

use App\Http\Controllers\Controller;
use App\Services\Printer\ReceiptPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-waiter-app configuration. Returns the printer settings (IP / port /
 * width / print_path) so the device can talk to the network printer
 * directly — bypassing the cloud server which usually can't reach the
 * restaurant LAN. The waiter app fetches this at login + on every
 * dashboard refresh so an admin-side IP change propagates within one
 * pull-to-refresh cycle.
 */
class WaiterConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('waiter_api');
        if (!$admin) {
            return response()->json([
                'errors' => [['code' => 'unauthorized', 'message' => 'Authentication required.']],
            ], 401);
        }

        $cfg = ReceiptPrinter::config();
        // Branch-scoped admins see the same global printer config; per-
        // branch overrides can be added as a future enhancement when one
        // restaurant has multiple kitchens with different printer IPs.
        return response()->json([
            'printer' => [
                'enabled'     => $cfg['enabled'],
                'ip'          => $cfg['ip'],
                'port'        => $cfg['port'],
                'width_chars' => $cfg['width_chars'],
                'print_path'  => $cfg['print_path'],
                'profile'     => $cfg['profile'],
            ],
            'branch_id' => $admin->branch_id,
        ]);
    }
}
