<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Frozen snapshot of one employee's pay for one payroll run. Created
 * at lock time. Cannot be re-derived from current data — that's the
 * whole point.
 */
class Payslip extends Model
{
    protected $table = 'payslips';

    protected $fillable = [
        'run_id',
        'admin_id',
        'branch_id',
        'days_clocked',
        'calendar_days',
        'attendance_minutes',
        'prorated_basic',
        'prorated_allowance',
        'prorated_deduction',
        'tip_share',
        'advance_recovery',
        'gross',
        'net',
        'line_items_json',
        'employee_snapshot_json',
        'paid_at',
        'paid_method',
        'notes',
    ];

    protected $casts = [
        'paid_at'                => 'datetime',
        'line_items_json'        => 'array',
        'employee_snapshot_json' => 'array',
        'prorated_basic'         => 'decimal:2',
        'prorated_allowance'     => 'decimal:2',
        'prorated_deduction'     => 'decimal:2',
        'tip_share'              => 'decimal:2',
        'advance_recovery'       => 'decimal:2',
        'gross'                  => 'decimal:2',
        'net'                    => 'decimal:2',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function isPaid(): bool { return $this->paid_at !== null; }
}
