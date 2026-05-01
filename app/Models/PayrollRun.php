<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One payroll run = one period × one branch. Lifecycle:
 *   draft   → live computation, no commitment
 *   locked  → payslips snapshotted, advance balances reduced
 *   paid    → all payslips marked paid (or a ceremonial overall flag)
 */
class PayrollRun extends Model
{
    protected $table = 'payroll_runs';

    protected $fillable = [
        'branch_id',
        'period_from',
        'period_to',
        'status',
        'total_gross',
        'total_deductions',
        'total_advances',
        'total_tips',
        'total_net',
        'created_by_admin_id',
        'locked_by_admin_id',
        'locked_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'period_from'      => 'date',
        'period_to'        => 'date',
        'locked_at'        => 'datetime',
        'paid_at'          => 'datetime',
        'total_gross'      => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_advances'   => 'decimal:2',
        'total_tips'       => 'decimal:2',
        'total_net'        => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'locked_by_admin_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'run_id');
    }

    public function isDraft(): bool   { return $this->status === 'draft'; }
    public function isLocked(): bool  { return in_array($this->status, ['locked', 'paid'], true); }
    public function isPaid(): bool    { return $this->status === 'paid'; }
}
