<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Models\AttendanceLog;
use App\Models\WorkSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Attendance API for the My Lahab staff app.
 *
 * Anti-fraud design (matches the office decision documented in the
 * Phase 1 spec):
 *   1. Branch QR — staff scans the printed QR at the time-clock spot.
 *      The QR encodes `branches.attendance_qr_token`. We require it
 *      match the staff's assigned branch token before recording.
 *   2. Selfie — required at clock-in. Stored under attendance/<file>.
 *      Acts as a deterrent + manual review trail. Skipped at clock-out.
 *   3. GPS proximity — if the device sent lat/lng, we check it falls
 *      inside `branches.attendance_geo_radius_m` of the branch coords.
 *      Skipped silently if the branch has no coords saved (avoids
 *      blocking attendance in branches we haven't geo-tagged yet).
 *
 * Idempotency: a second clock-in while a row is already open is
 * rejected; a clock-out with no open row is rejected. The app should
 * call `/today` first to render the right button.
 */
class StaffAttendanceController extends Controller
{
    public function today(): JsonResponse
    {
        $admin = auth('staff_api')->user();

        $open = AttendanceLog::openFor($admin->id);

        $todayLogs = AttendanceLog::query()
            ->where('admin_id', $admin->id)
            ->whereDate('clock_in_at', now()->toDateString())
            ->orderByDesc('clock_in_at')
            ->get();

        $sched = WorkSchedule::forAdminOnDate($admin->id, now());

        return response()->json([
            'now'             => now()->toIso8601String(),
            'open_log'        => $open ? $this->logPayload($open) : null,
            'logs_today'      => $todayLogs->map(fn ($l) => $this->logPayload($l))->values(),
            'scheduled_today' => $sched ? [
                'shift_start'   => $sched->shift_start,
                'shift_end'     => $sched->shift_end,
                'is_off_day'    => (bool) $sched->is_off_day,
                'grace_minutes' => (int) $sched->grace_minutes,
            ] : null,
        ]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        $admin = auth('staff_api')->user();

        $validator = Validator::make($request->all(), [
            'qr_token' => 'required|string',
            // Hard cap matches the client-side PhotoCompressor target.
            // Anything larger means compression failed → fail loud so we
            // can fix the client rather than burn server storage.
            'selfie'   => 'required|image|max:1024',
            'lat'      => 'nullable|numeric|between:-90,90',
            'lng'      => 'nullable|numeric|between:-180,180',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if (! $admin->branch_id) {
            return response()->json([
                'errors' => [['code' => 'no-branch', 'message' => 'No branch assigned. Contact office.']],
            ], 422);
        }

        $branch = Branch::find($admin->branch_id);
        if (! $branch) {
            return response()->json([
                'errors' => [['code' => 'no-branch', 'message' => 'Branch not found.']],
            ], 422);
        }

        if (! $branch->attendance_qr_token || ! hash_equals((string) $branch->attendance_qr_token, (string) $request->input('qr_token'))) {
            return response()->json([
                'errors' => [['code' => 'qr-mismatch', 'message' => 'This QR code is not for your branch.']],
            ], 422);
        }

        $geoError = $this->geoCheck($branch, $request->input('lat'), $request->input('lng'));
        if ($geoError) return $geoError;

        if (AttendanceLog::openFor($admin->id)) {
            return response()->json([
                'errors' => [['code' => 'already-open', 'message' => 'You are already clocked in.']],
            ], 409);
        }

        $selfiePath = $this->storeSelfie($request);

        $log = AttendanceLog::create([
            'admin_id'     => $admin->id,
            'branch_id'    => $admin->branch_id,
            'clock_in_at'  => now(),
            'method'       => 'mobile_qr',
            'selfie_path'  => $selfiePath,
            'clock_in_lat' => $request->input('lat'),
            'clock_in_lng' => $request->input('lng'),
        ]);

        return response()->json([
            'message'  => 'Clocked in.',
            'open_log' => $this->logPayload($log->fresh()),
        ]);
    }

    public function clockOut(Request $request): JsonResponse
    {
        $admin = auth('staff_api')->user();

        $validator = Validator::make($request->all(), [
            'qr_token' => 'required|string',
            'lat'      => 'nullable|numeric|between:-90,90',
            'lng'      => 'nullable|numeric|between:-180,180',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $open = AttendanceLog::openFor($admin->id);
        if (! $open) {
            return response()->json([
                'errors' => [['code' => 'no-open', 'message' => 'You are not clocked in.']],
            ], 409);
        }

        $branch = Branch::find($open->branch_id);
        // QR check is against the row's branch (where they clocked IN), not
        // the staff's current branch_id, in case the office moves them mid-shift.
        if (! $branch || ! $branch->attendance_qr_token || ! hash_equals((string) $branch->attendance_qr_token, (string) $request->input('qr_token'))) {
            return response()->json([
                'errors' => [['code' => 'qr-mismatch', 'message' => 'Scan the QR at the branch where you clocked in.']],
            ], 422);
        }

        $geoError = $this->geoCheck($branch, $request->input('lat'), $request->input('lng'));
        if ($geoError) return $geoError;

        $open->update([
            'clock_out_at'  => now(),
            'clock_out_lat' => $request->input('lat'),
            'clock_out_lng' => $request->input('lng'),
        ]);

        return response()->json([
            'message'   => 'Clocked out.',
            'last_log'  => $this->logPayload($open->fresh()),
            'open_log'  => null,
        ]);
    }

    /**
     * Returns a JsonResponse on geo failure, null on success or skip.
     * Skipped silently if the branch hasn't been geo-tagged yet so we
     * don't block legitimate attendance in newly-onboarded branches.
     */
    private function geoCheck(Branch $branch, $lat, $lng): ?JsonResponse
    {
        $blat = is_numeric($branch->latitude) ? (float) $branch->latitude : null;
        $blng = is_numeric($branch->longitude) ? (float) $branch->longitude : null;
        if ($blat === null || $blng === null) return null;
        if ($lat === null || $lng === null) return null;

        $distance = $this->haversine((float) $lat, (float) $lng, $blat, $blng);
        $radius = (int) ($branch->attendance_geo_radius_m ?? 150);
        if ($distance > $radius) {
            return response()->json([
                'errors' => [[
                    'code'    => 'too-far',
                    'message' => sprintf('You are %d m from %s. Move closer to clock in.', (int) $distance, $branch->name),
                ]],
            ], 422);
        }
        return null;
    }

    /** Great-circle distance in metres. */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function storeSelfie(Request $request): ?string
    {
        if (! $request->hasFile('selfie')) return null;
        $file = $request->file('selfie');
        $name = uniqid('att_', true) . '.' . $file->getClientOriginalExtension();
        $file->storeAs('attendance', $name, 'public');
        return 'attendance/' . $name;
    }

    private function logPayload(AttendanceLog $log): array
    {
        return [
            'id'             => $log->id,
            'branch_id'      => $log->branch_id,
            'clock_in_at'    => optional($log->clock_in_at)->toIso8601String(),
            'clock_out_at'   => optional($log->clock_out_at)?->toIso8601String(),
            'method'         => $log->method,
            'worked_minutes' => $log->workedMinutes(),
            'selfie_url'     => $log->selfie_path
                ? asset('storage/app/public/' . $log->selfie_path)
                : null,
        ];
    }
}
