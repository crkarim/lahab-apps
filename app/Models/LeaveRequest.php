<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One leave request from a staff member. Lifecycle:
 *   pending   → awaiting Master Admin / branch manager decision
 *   approved  → counts against balance, feeds payroll proration
 *               (paid types) and is treated as worked-day for
 *               attendance reporting
 *   rejected  → dead row, no balance impact
 *   cancelled → employee withdrew before review (only allowed
 *               while still pending)
 */
class LeaveRequest extends Model
{
    protected $table = 'leave_requests';

    protected $fillable = [
        'admin_id',
        'leave_type_id',
        'branch_id',
        'from_date',
        'to_date',
        'days',
        'reason',
        'status',
        'reviewed_by_admin_id',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'from_date'   => 'date',
        'to_date'     => 'date',
        'days'        => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    /**
     * Sum of approved leave days this calendar year for an employee
     * + leave type. Used to compute available balance.
     */
    public static function takenThisYear(int $adminId, int $typeId, ?int $year = null): int
    {
        $year = $year ?? (int) now()->format('Y');
        return (int) self::query()
            ->where('admin_id', $adminId)
            ->where('leave_type_id', $typeId)
            ->where('status', 'approved')
            ->whereYear('from_date', $year)
            ->sum('days');
    }
}
