<?php

namespace App\Http\Controllers\Api\V1\Waiter;

use App\CentralLogics\Helpers;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Model\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menu surface for the waiter app — categories + paginated products.
 * Mounted under `auth:waiter_api + waiter_branch`, so by the time we get
 * here `Config::get('branch_id')` already points at the staff member's
 * branch and the existing ProductLogic helpers Just Work.
 */
class WaiterMenuController extends Controller
{
    /** Top-level categories that have at least one product available. */
    public function categories(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->where('parent_id', 0)
            ->where('status', 1)
            ->orderBy('priority', 'asc')
            ->get(['id', 'name', 'image', 'priority']);

        return response()->json(['categories' => $categories]);
    }

    /**
     * Paginated product list. Honours the same filter set as the customer
     * "/products/latest" endpoint so we can swap parsing code with the
     * existing apps later. Auto-resolves variations / addons / category
     * arrays via Helpers::product_data_formatting.
     *
     * Query params (all optional):
     *   limit (default 20), offset (default 1)
     *   category_id   — single id
     *   product_type  — 'veg' | 'non_veg'
     *   is_halal      — 1/0
     *   name          — search across name (LIKE %term%)
     *   sort_by       — 'popular' | 'price_high_to_low' | 'price_low_to_high'
     */
    public function products(Request $request): JsonResponse
    {
        $limit  = (int) ($request->input('limit', 20));
        $offset = (int) ($request->input('offset', 1));

        $result = ProductLogic::get_latest_products(
            $limit,
            $offset,
            $request->input('product_type'),
            $request->input('name'),
            $request->input('category_id'),
            $request->input('sort_by'),
            $request->input('is_halal'),
        );
        $result['products'] = Helpers::product_data_formatting($result['products'], true);

        return response()->json($result);
    }
}
