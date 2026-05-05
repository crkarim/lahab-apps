<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One execution of a checklist on a given run_date by a specific staff.
 *
 * Lifecycle:
 *   started_at set, completed_at NULL → in progress
 *   completed_at set                  → done (all required items checked)
 */
class ChecklistRun extends Model
{
    protected $table = 'checklist_runs';

    protected $fillable = [
        'template_id', 'branch_id', 'started_by_admin_id',
        'started_at', 'completed_at', 'run_date', 'notes',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'run_date'     => 'date',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'started_by_admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistRunItem::class, 'run_id');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
