<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffNotice;
use App\Models\StaffNoticeRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Notice feed for the My Lahab staff app.
 *
 * Visibility = active (published in past, not yet expired) AND
 * (branch matches the staff's branch OR notice is global).
 *
 * Each notice carries a `is_read` boolean derived from the
 * staff_notice_reads table so the home tab can render an unread badge
 * without a second round-trip.
 */
class StaffNoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $perPage = (int) min(50, max(5, (int) $request->query('per_page', 20)));

        $page = StaffNotice::query()
            ->active()
            ->forStaff($admin->branch_id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $readIds = StaffNoticeRead::query()
            ->where('admin_id', $admin->id)
            ->whereIn('staff_notice_id', $page->pluck('id'))
            ->pluck('staff_notice_id')
            ->all();
        $readSet = array_flip($readIds);

        $items = $page->getCollection()->map(function (StaffNotice $n) use ($readSet) {
            return [
                'id'           => $n->id,
                'title'        => $n->title,
                'body'         => $n->body,
                'image_url'    => $n->image ? asset('storage/app/public/' . $n->image) : null,
                'is_pinned'    => (bool) $n->is_pinned,
                'is_read'      => isset($readSet[$n->id]),
                'branch_scope' => $n->branch_id ? 'branch' : 'global',
                'published_at' => optional($n->published_at)->toIso8601String(),
                'expires_at'   => optional($n->expires_at)->toIso8601String(),
            ];
        });

        $unreadCount = StaffNotice::query()
            ->active()
            ->forStaff($admin->branch_id)
            ->whereNotIn('id', function ($q) use ($admin) {
                $q->select('staff_notice_id')
                    ->from('staff_notice_reads')
                    ->where('admin_id', $admin->id);
            })
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
            'data'         => $items,
            'meta'         => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $admin = auth('staff_api')->user();

        $exists = StaffNotice::query()
            ->active()
            ->forStaff($admin->branch_id)
            ->where('id', $id)
            ->exists();

        if (! $exists) {
            return response()->json(['errors' => [['code' => 'not-found', 'message' => 'Notice not found.']]], 404);
        }

        // Idempotent: insert-or-ignore via the unique constraint.
        StaffNoticeRead::firstOrCreate(
            ['staff_notice_id' => $id, 'admin_id' => $admin->id],
            ['read_at' => now()],
        );

        return response()->json(['message' => 'Marked as read.']);
    }
}
