<?php

namespace App\Models;

use App\Model\Admin;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (employee, day-of-week). Drives the late / early /
 * overtime classification on attendance_logs.
 *
 * Day codes follow MySQL DAYOFWEEK / PHP-like "1=Sun..7=Sat" so we
 * can drop them straight into queries without translation.
 */
class WorkSchedule extends Model
{
    protected $table = 'work_schedules';

    public const DAYS = [
        1 => 'Sunday',
        2 => 'Monday',
        3 => 'Tuesday',
        4 => 'Wednesday',
        5 => 'Thursday',
        6 => 'Friday',
        7 => 'Saturday',
    ];

    /**
     * BD Labour Act default seed — Sun-Thu and Saturday on duty
     * 09:00→18:00 (1 hour break), Friday off. Restaurant operators
     * can override per-employee from the schedule editor.
     */
    public const BD_DEFAULT = [
        1 => ['shift_start' => '09:00', 'shift_end' => '18:00', 'is_off_day' => false], // Sun
        2 => ['shift_start' => '09:00', 'shift_end' => '18:00', 'is_off_day' => false], // Mon
        3 => ['shift_start' => '09:00', 'shift_end' => '18:00', 'is_off_day' => false], // Tue
        4 => ['shift_start' => '09:00', 'shift_end' => '18:00', 'is_off_day' => false], // Wed
        5 => ['shift_start' => '09:00', 'shift_end' => '18:00', 'is_off_day' => false], // Thu
        6 => ['shift_start' => null,    'shift_end' => null,    'is_off_day' => true],  // Fri (off)
        7 => ['shift_start' => '09:00', 'shift_end' => '18:00', 'is_off_day' => false], // Sat
    ];

    protected $fillable = [
        'admin_id',
        'day_of_week',
        'shift_start',
        'shift_end',
        'is_off_day',
        'break_minutes',
        'grace_minutes',
    ];

    protected $casts = [
        'is_off_day'    => 'boolean',
        'break_minutes' => 'integer',
        'grace_minutes' => 'integer',
        'day_of_week'   => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /** Resolve the schedule row for an employee for a given calendar date. */
    public static function forAdminOnDate(int $adminId, Carbon $date): ?self
    {
        // PHP date("w"): 0 = Sunday → +1 to match our 1..7 convention.
        $dow = ((int) $date->format('w')) + 1;
        return self::where('admin_id', $adminId)->where('day_of_week', $dow)->first();
    }

    /** Build a Carbon for the shift_start on a specific calendar date. */
    public function startOn(Carbon $date): ?Carbon
    {
        if (!$this->shift_start) return null;
        return Carbon::parse($date->toDateString() . ' ' . $this->shift_start);
    }

    /** Build a Carbon for the shift_end on a specific calendar date.
     *  Handles overnight shifts (end < start → next day). */
    public function endOn(Carbon $date): ?Carbon
    {
        if (!$this->shift_end) return null;
        $end = Carbon::parse($date->toDateString() . ' ' . $this->shift_end);
        $start = $this->startOn($date);
        if ($start && $end->lessThanOrEqualTo($start)) {
            $end->addDay(); // overnight shift
        }
        return $end;
    }
}
