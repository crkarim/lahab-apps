<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistTemplateItem extends Model
{
    protected $table = 'checklist_template_items';

    protected $fillable = [
        'template_id', 'label', 'sort_order', 'is_required', 'requires_photo', 'notes',
        'assigned_designation_id', 'assigned_admin_id', 'scheduled_time',
    ];

    protected $casts = [
        'is_required'    => 'boolean',
        'requires_photo' => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function assignedDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'assigned_designation_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(\App\Model\Admin::class, 'assigned_admin_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }
}
