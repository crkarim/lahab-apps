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

    /**
     * Lazy-cache the schedule row used for this attendance row's
     * date — avoids 3 separate queries when you call lateMinutes(),
     * earlyMinutes(), and overtimeMinutes() in succession.
     */
    private ?WorkSchedule $_schedCache = null;
    private bool $_schedCached = false;

    public function schedule(): ?WorkSchedule
    {
        if ($this->_schedCached) return $this->_schedCache;
        $this->_schedCached = true;
        if (!$this->clock_in_at) return $this->_schedCache = null;
        return $this->_schedCache = WorkSchedule::forAdminOnDate($this->admin_id, $this->clock_in_at);
    }

    /**
     * Minutes late = how far the clock-in was past the scheduled
     * start, beyond the grace window. Returns 0 if on time, no
     * schedule, or off-day.
     */
    public function lateMinutes(): int
    {
        $sched = $this->schedule();
        if (!$sched || $sched->is_off_day) return 0;
        $start = $sched->startOn($this->clock_in_at);
        if (!$start) return 0;
        $tolerance = $start->copy()->addMinutes((int) $sched->grace_minutes);
        if ($this->clock_in_at->lessThanOrEqualTo($tolerance)) return 0;
        return max(0, (int) $start->diffInMinutes($this->clock_in_at));
    }

    /**
     * Minutes left early = how far the clock-out was BEFORE the
     * scheduled end. Returns 0 if punched out on time, still open,
     * no schedule, or off-day.
     */
    public function earlyMinutes(): int
    {
        $sched = $this->schedule();
        if (!$sched || $sched->is_off_day) return 0;
        if (!$this->clock_out_at) return 0;
        $end = $sched->endOn($this->clock_in_at);
        if (!$end) return 0;
        $tolerance = $end->copy()->subMinutes((int) $sched->grace_minutes);
        if ($this->clock_out_at->greaterThanOrEqualTo($tolerance)) return 0;
        return max(0, (int) $this->clock_out_at->diffInMinutes($end));
    }

    /**
     * Overtime minutes = time worked past the scheduled end. On an
     * off-day, ALL worked minutes count as overtime (BD Labour Act
     * Sec 108 — overtime at 2× ordinary wage).
     */
    public function overtimeMinutes(): int
    {
        $sched = $this->schedule();
        if (!$sched) return 0;
        if (!$this->clock_out_at) return 0;

        if ($sched->is_off_day) {
            // Off-day work = all worked minutes are OT.
            return $this->workedMinutes();
        }
        $end = $sched->endOn($this->clock_in_at);
        if (!$end) return 0;
        if ($this->clock_out_at->lessThanOrEqualTo($end)) return 0;
        return max(0, (int) $end->diffInMinutes($this->clock_out_at));
    }
}
