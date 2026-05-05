<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-staff read receipt. Existence of a row = "this staff has read this
 * notice"; absence of a row = unread (drives the home-tab unread badge).
 */
class StaffNoticeRead extends Model
{
    protected $table = 'staff_notice_reads';

    public $timestamps = false;

    protected $fillable = [
        'staff_notice_id',
        'admin_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
