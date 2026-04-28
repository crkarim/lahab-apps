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
 * Auth surface for the Waiter Flutter app. Issues Passport personal-access
 * tokens against the Admin (employee) model so the same staff record that
 * places orders in the admin POS can place them from a tablet.
 *
 * Routes mounted under /api/v1/auth/waiter (login) and /api/v1/waiter/*
 * (token-protected). The `waiter_api` guard (config/auth.php) maps tokens
 * back to admins.
 */
class WaiterAuthController extends Controller
{
    public function __construct(private Admin $admin) {}

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_or_phone' => 'required|string',
            'password'       => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $identifier = trim((string) $request->input('email_or_phone'));

        $admin = $this->admin
            ->where('status', 1)
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)->orWhere('phone', $identifier);
            })
            ->first();

        if (! $admin || ! Hash::check($request->input('password'), $admin->password)) {
            return response()->json([
                'errors' => [['code' => 'auth-001', 'message' => translate('Invalid credential.')]],
            ], 401);
        }

        $token = $admin->createToken('WaiterApp')->accessToken;

        return response()->json([
            'token'   => $token,
            'user'    => $this->profilePayload($admin),
            'message' => translate('Successfully login.'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = auth('waiter_api')->user();
        return response()->json(['user' => $this->profilePayload($admin)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = auth('waiter_api')->user()?->token();
        $token?->revoke();

        return response()->json([
            'message' => translate('You have been successfully logged out!'),
        ]);
    }

    /**
     * Profile shape the Flutter app expects. We deliberately omit password
     * + identity_image and inline the role name so the client doesn't need
     * a follow-up request just to render the user pill.
     */
    private function profilePayload(Admin $admin): array
    {
        $admin->loadMissing('role', 'branch');
        return [
            'id'          => $admin->id,
            'f_name'      => $admin->f_name,
            'l_name'      => $admin->l_name,
            'email'       => $admin->email,
            'phone'       => $admin->phone,
            'image'       => $admin->image_full_path,
            'role_id'     => $admin->admin_role_id,
            'role_name'   => $admin->role?->name,
            'branch_id'   => $admin->branch_id,
            'branch_name' => $admin->branch?->name,
        ];
    }
}
