<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Auth surface for the My Lahab staff app. Phone + 4-6 digit PIN against
 * the Admin (employee) model. PINs live in `admins.app_pin_hash` (bcrypt);
 * `admins.app_login_enabled` is the office-managed kill switch — staff can
 * be deactivated without touching their admin-panel credentials.
 *
 * Routes mounted under /api/v1/staff/auth (login) and /api/v1/staff/*
 * (token-protected). The `staff_api` guard (config/auth.php) maps tokens
 * back to admins. Distinct from waiter_api so revoking one doesn't take
 * down the other.
 */
class StaffAuthController extends Controller
{
    public function __construct(private Admin $admin) {}

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'pin'   => 'required|string|min:4|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $phone = trim((string) $request->input('phone'));

        // Accept the phone in any of the common Bangladesh formats so
        // the staff doesn't have to remember whether the office stored
        // their number with a country prefix or not. We build a small
        // candidate list and match against `admins.phone` exactly —
        // the column already has a mix of `01...` and `+8801...` rows.
        $admin = $this->admin
            ->where('status', 1)
            ->where('app_login_enabled', 1)
            ->whereIn('phone', $this->phoneCandidates($phone))
            ->first();

        // Unified error for "no such phone", "not enabled", and "wrong PIN"
        // so we don't leak which staff phones exist.
        if (! $admin || ! $admin->app_pin_hash || ! Hash::check($request->input('pin'), $admin->app_pin_hash)) {
            return response()->json([
                'errors' => [['code' => 'auth-001', 'message' => translate('Invalid phone or PIN.')]],
            ], 401);
        }

        $token = $admin->createToken('MyLahabApp')->accessToken;

        return response()->json([
            'token'   => $token,
            'user'    => $this->profilePayload($admin),
            'message' => translate('Successfully login.'),
        ]);
    }

    public function me(): JsonResponse
    {
        $admin = auth('staff_api')->user();
        return response()->json(['user' => $this->profilePayload($admin)]);
    }

    public function logout(): JsonResponse
    {
        auth('staff_api')->user()?->token()?->revoke();
        return response()->json(['message' => translate('You have been successfully logged out!')]);
    }

    /**
     * Persist the device's FCM token onto admins.fcm_token. Mirrors the
     * pattern used by the rider app, against the same column.
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|min:10',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $admin = auth('staff_api')->user();
        $admin->fcm_token = $request->input('fcm_token');
        $admin->save();

        return response()->json(['message' => 'FCM token updated.']);
    }

    /**
     * Build the list of equivalent phone strings to look up. Strips
     * non-digits, then derives the canonical Bangladesh forms:
     *   - As typed (after digit-only strip)
     *   - As typed with leading 0
     *   - +88 / 88 prefixed forms
     * Returns a unique list so the SQL `IN (...)` is short.
     */
    private function phoneCandidates(string $raw): array
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') return [$raw];

        // National form: 11 digits starting with 0 (e.g. 01715253043)
        // International form: 13 digits starting with 88 (e.g. 8801715253043)
        // We also support "017..." typed without the leading 0 (rare but seen).
        $national = $digits;
        if (str_starts_with($digits, '88')) {
            $national = substr($digits, 2);
        }
        if (! str_starts_with($national, '0') && strlen($national) === 10) {
            $national = '0' . $national;
        }

        $candidates = array_unique([
            $raw,
            $digits,
            $national,
            '+88' . $national,
            '88' . $national,
            '+880' . ltrim($national, '0'),
        ]);

        return array_values(array_filter($candidates, fn ($s) => $s !== ''));
    }

    /**
     * Profile shape the Flutter app expects. Includes today's expected
     * shift (work_schedules) and whether there's an open attendance row
     * so the home screen can render "Clock in" vs "Clock out" without a
     * second round-trip.
     */
    private function profilePayload(Admin $admin): array
    {
        $admin->loadMissing('role', 'branch', 'department', 'designationRef');

        return [
            'id'           => $admin->id,
            'employee_code'=> $admin->employee_code,
            'f_name'       => $admin->f_name,
            'l_name'       => $admin->l_name,
            'email'        => $admin->email,
            'phone'        => $admin->phone,
            // The codebase serves storage URLs at /storage/app/public/<dir>/<file>
            // via the symlink public/storage → ../storage. Mirror that
            // convention so existing admin-uploaded profile pictures load.
            'image'        => $admin->image
                ? asset('storage/app/public/admin/' . $admin->image)
                : null,
            'role_id'      => $admin->admin_role_id,
            'role_name'    => $admin->role?->name,
            'branch_id'    => $admin->branch_id,
            'branch_name'  => $admin->branch?->name,
            'department'   => $admin->department?->name,
            'designation'  => $admin->designationRef?->name ?? $admin->designation,
            // Staff-app feature flags. Only the surfaces that conditionally
            // appear in the Flutter UI need to be enumerated here — the
            // app uses these to show/hide tabs (e.g. management dashboard).
            'permissions'  => $this->staffPermissions($admin),
        ];
    }

    /**
     * Compute the subset of MANAGEMENT_SECTION keys the staff app cares
     * about for this user. Master Admin (role 1) wins on every key;
     * other roles need the key listed in admin_roles.module_access JSON.
     *
     * Inlined (rather than using Helpers::module_permission_check) because
     * that helper reads from the auth('admin') session, which doesn't
     * exist on a Passport API request.
     */
    private function staffPermissions(Admin $admin): array
    {
        $keys = ['management_dashboard'];

        $isMaster = (int) $admin->admin_role_id === 1;
        $allowed = [];
        if (! $isMaster) {
            $admin->loadMissing('role');
            $raw = $admin->role?->module_access;
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) $allowed = $decoded;
        }

        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $isMaster || in_array($k, $allowed, true);
        }
        return $out;
    }
}
