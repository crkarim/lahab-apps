<?php

namespace App\Models;

use App\Model\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistRunSubmission extends Model
{
    protected $table = 'checklist_run_submissions';

    public $timestamps = false;

    protected $fillable = ['run_id', 'admin_id', 'admin_name', 'submitted_at', 'note'];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ChecklistRun::class, 'run_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
