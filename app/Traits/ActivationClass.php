<?php
namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ActivationClass
{
    public function dmvf($request)
    {
        session()->put('purchase_key', $request['purchase_key'] ?? '');
        session()->put('username', $request['username'] ?? '');
        return 'step3';
    }

    public function actch(): JsonResponse
    {
        return response()->json(['active' => 1]);
    }

    public function is_local(): bool
    {
        return true;
    }
}
