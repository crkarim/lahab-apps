<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One advance / loan given to a staff member, with a per-run recovery
 * schedule (e.g. give Tk 20,000 today, recover Tk 4,000 each payroll
 * run for 5 runs). Balance decreases as runs lock; status flips to
 * `recovered` when balance hits zero.
 *
 * Until Phase 4.3 ships the actual run lifecycle, balance is decreased
 * manually (cancel + new advance) — Phase 4.2 just lays the data
 * model + UI down so the Payroll prep view can show projected
 * recoveries today.
 */
class SalaryAdvance extends Model
{
    protected $table = 'salary_advances';

    protected $fillable = [
        'admin_id',
        'branch_id',
        'amount',
        'recovery_per_run',
        'balance',
        'taken_at',
        'reason',
        'status',
        'recorded_by_admin_id',
        'recovered_by_run_id',
        'notes',
        // Phase 8.5c — cash account the advance came out of. OUT to
        // this account on store; IN to it (or a chosen one) on
        // manual recovery.
        'source_account_id',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'recovery_per_run' => 'decimal:2',
        'balance'          => 'decimal:2',
        'taken_at'         => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }

    /**
     * Active advances summed for one employee — sum of remaining
     * balance + projected next-run recovery. Used by the Payroll
     * prep view to surface what'll be deducted.
     */
    public static function activeForAdmin(int $adminId)
    {
        return self::query()
            ->where('admin_id', $adminId)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->orderBy('taken_at');
    }

    /**
     * How much will be recovered in the next payroll run for this
     * employee — sum of recovery_per_run capped at the remaining
     * balance per advance.
     */
    public static function projectedRecoveryFor(int $adminId): float
    {
        $advances = self::activeForAdmin($adminId)->get();
        $total = 0.0;
        foreach ($advances as $a) {
            $total += min((float) $a->recovery_per_run, (float) $a->balance);
        }
        return round($total, 2);
    }
}
