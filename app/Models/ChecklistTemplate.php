<?php

namespace App\Models;

use App\Model\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One reusable checklist (e.g. "Morning Open"). The actual lines live
 * on `checklist_template_items`; runtime executions live on
 * `checklist_runs` + `checklist_run_items`.
 */
class ChecklistTemplate extends Model
{
    protected $table = 'checklist_templates';

    protected $fillable = [
        'branch_id', 'name', 'kind', 'sort_order',
        'is_active', 'created_by_admin_id', 'notes',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class, 'template_id')
            ->orderBy('sort_order')->orderBy('id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ChecklistRun::class, 'template_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Visible to a given branch: branch-scoped match or global. */
    public function scopeForBranch(Builder $q, ?int $branchId): Builder
    {
        return $q->where(function ($qq) use ($branchId) {
            $qq->whereNull('branch_id');
            if ($branchId !== null) $qq->orWhere('branch_id', $branchId);
        });
    }
}
