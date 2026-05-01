<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One department in the org chart (Kitchen, Service, Bar, Accounts...).
 *
 * branch_id NULL = HQ-wide, every branch sees it. branch_id set means
 * branch-specific (e.g. "Kitchen — Gulshan branch" with its own head).
 *
 * head_admin_id is the org-chart label (Department Head). It's *not*
 * automatically the leave approver — that's `admins.reports_to_admin_id`,
 * which can target the head, an asst manager, or a shift supervisor.
 */
class Department extends Model
{
    protected $table = 'departments';

    protected $fillable = [
        'name', 'code', 'branch_id', 'head_admin_id',
        'color', 'is_active', 'sort_order', 'description',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'head_admin_id');
    }

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class, 'department_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Admin::class, 'department_id');
    }

    /** Visible-to-this-viewer departments. branch null = HQ-wide. */
    public static function visibleTo(?Admin $admin)
    {
        $branchId = $admin?->branch_id;
        return self::query()
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
