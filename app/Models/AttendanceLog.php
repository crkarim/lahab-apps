<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per clock-in event for a staff member. `clock_out_at` is
 * null while the row is "open" (staff currently on duty).
 *
 * Worked-minutes derive from `clock_in_at` → `clock_out_at` (or
 * `now()` if still open). We don't denormalise this onto the row to
 * keep the source of truth in the timestamps.
 */
class AttendanceLog extends Model
{
    protected $table = 'attendance_logs';

    protected $fillable = [
        'admin_id',
        'branch_id',
        'clock_in_at',
        'clock_out_at',
        'shift_id',
        'method',
        'notes',
    ];

    protected $casts = [
        'clock_in_at'  => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function isOpen(): bool
    {
        return $this->clock_out_at === null;
    }

    /** Worked time in minutes; counts up to now() when still open. */
    public function workedMinutes(): int
    {
        $start = $this->clock_in_at;
        $end   = $this->clock_out_at ?? now();
        if (!$start) return 0;
        return max(0, (int) $start->diffInMinutes($end));
    }

    /** Find the open (no clock_out_at) row for a given admin, if any. */
    public static function openFor(int $adminId): ?self
    {
        return self::query()
            ->where('admin_id', $adminId)
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();
    }
}
