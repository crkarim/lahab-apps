<?php

namespace App\Models;

use App\Model\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistRunItem extends Model
{
    protected $table = 'checklist_run_items';

    protected $fillable = [
        'run_id', 'template_item_id', 'label_snapshot', 'is_required', 'requires_photo',
        'checked_at', 'checked_by_admin_id', 'photo_path', 'note',
        'assigned_designation_id', 'assigned_designation_name',
        'assigned_admin_id', 'assigned_admin_name',
        'scheduled_time', 'reminder_sent_at',
    ];

    protected $casts = [
        'checked_at'       => 'datetime',
        'reminder_sent_at' => 'datetime',
        'is_required'      => 'boolean',
        'requires_photo'   => 'boolean',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ChecklistRun::class, 'run_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'checked_by_admin_id');
    }

    public function isChecked(): bool
    {
        return $this->checked_at !== null;
    }
}
