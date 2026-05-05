<?php

namespace App\Models;

use App\Model\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Notice posted from the admin panel that surfaces in the My Lahab
 * staff app's Notices tab + as an FCM push.
 *
 *   branch_id  : null = global (shows for everyone), set = branch-only
 *   published_at / expires_at : window during which the notice is visible
 *   is_pinned  : sticks to the top of the list
 */
class StaffNotice extends Model
{
    protected $table = 'staff_notices';

    protected $fillable = [
        'branch_id',
        'title',
        'body',
        'image',
        'published_at',
        'expires_at',
        'is_pinned',
        'posted_by_admin_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at'   => 'datetime',
        'is_pinned'    => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(StaffNoticeRead::class, 'staff_notice_id');
    }

    /** Visible right now: published in the past, not yet expired. */
    public function scopeActive(Builder $q): Builder
    {
        $now = now();
        return $q
            ->where(function ($qq) use ($now) {
                $qq->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function ($qq) use ($now) {
                $qq->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });
    }

    /** Visible to a given staff: branch match or global. */
    public function scopeForStaff(Builder $q, ?int $branchId): Builder
    {
        return $q->where(function ($qq) use ($branchId) {
            $qq->whereNull('branch_id');
            if ($branchId !== null) {
                $qq->orWhere('branch_id', $branchId);
            }
        });
    }
}
