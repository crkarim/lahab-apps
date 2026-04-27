<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\BusinessSetting;
use App\Services\Printer\ReceiptPrinter;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Admin-side wiring for the network thermal printer the waiter app will
 * eventually print receipts to. Phase 0 ships:
 *   - settings page (IP / port / width / enabled toggle)
 *   - "Print test page" action (POST → JSON, surfaced via toast)
 * Future phases add per-branch override + receipt-from-order printing.
 */
class PrinterController extends Controller
{
    public function index(): Renderable
    {
        $settings = ReceiptPrinter::config();
        return view('admin-views.business-settings.printer-setup', compact('settings'));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip'          => 'nullable|string|max:64',
            'port'        => 'nullable|integer|min:1|max:65535',
            'width_chars' => 'nullable|integer|min:24|max:64',
            'enabled'     => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());
            return back();
        }

        $payload = json_encode([
            'ip'              => trim((string) $request->input('ip', '')),
            'port'            => (int) $request->input('port', 9100),
            'width_chars'     => (int) $request->input('width_chars', 48),
            'enabled'         => (bool) $request->input('enabled', false),
            'timeout_seconds' => 5,
        ]);

        BusinessSetting::updateOrCreate(['key' => 'receipt_printer'], ['value' => $payload]);

        Toastr::success(translate('Receipt printer settings saved'));
        return back();
    }

    public function testPrint(): JsonResponse
    {
        $result = (new ReceiptPrinter())->printTestPage();

        return response()->json([
            'ok'      => $result['ok'],
            'message' => $result['ok']
                ? translate('Test page sent to the printer')
                : translate('Could not reach the printer') . ': ' . ($result['error'] ?? 'unknown error'),
        ], $result['ok'] ? 200 : 422);
    }
}
