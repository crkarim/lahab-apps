<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * GET /api/v1/staff/attendance/history
 *
 * Paginated attendance log scoped to the authenticated staff. Optional
 * `from`/`to` query params (YYYY-MM-DD); both default sensibly so the
 * default call returns the last 30 days. Aggregates total worked
 * minutes for the page so the Flutter view can render a header summary
 * without a second round-trip.
 */
class StaffAttendanceHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $perPage = (int) min(60, max(5, (int) $request->query('per_page', 30)));

        $to   = $request->query('to')   ? Carbon::parse($request->query('to'))->endOfDay()   : Carbon::now()->endOfDay();
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : Carbon::now()->subDays(30)->startOfDay();

        $page = AttendanceLog::query()
            ->where('admin_id', $admin->id)
            ->whereBetween('clock_in_at', [$from, $to])
            ->orderByDesc('clock_in_at')
            ->paginate($perPage);

        // Aggregate per-page so the header shows "X hours across Y days".
        $totalMinutes = 0;
        $dayKeys = [];
        $items = $page->getCollection()->map(function (AttendanceLog $log) use (&$totalMinutes, &$dayKeys) {
            $worked = $log->workedMinutes();
            $totalMinutes += $worked;
            if ($log->clock_in_at) {
                $dayKeys[$log->clock_in_at->toDateString()] = true;
            }
            return [
                'id'             => $log->id,
                'date'           => optional($log->clock_in_at)->toDateString(),
                'clock_in_at'    => optional($log->clock_in_at)->toIso8601String(),
                'clock_out_at'   => optional($log->clock_out_at)?->toIso8601String(),
                'method'         => $log->method,
                'worked_minutes' => $worked,
                'late_minutes'   => $log->lateMinutes(),
                'early_minutes'  => $log->earlyMinutes(),
                'overtime_minutes' => $log->overtimeMinutes(),
                'selfie_url'     => $log->selfie_path ? asset('storage/app/public/' . $log->selfie_path) : null,
            ];
        });

        return response()->json([
            'range'  => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'totals' => [
                'days'    => count($dayKeys),
                'minutes' => $totalMinutes,
            ],
            'data' => $items->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }
}
