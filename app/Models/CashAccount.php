<?php

namespace App\Models;

use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One "wallet" the company holds money in.
 *
 * Types:
 *   - cash    — physical safes, branch tills
 *   - bank    — DBBL, BRAC, City Bank current accounts
 *   - mfs     — bKash, Nagad, Rocket, Upay
 *   - cheque  — staging account for cheques received before clearance
 *
 * `current_balance` is denormalised. Controllers call `recomputeBalance()`
 * after every transaction insert/update/delete so the value stays in sync
 * with the underlying ledger; if drift is suspected, run it manually.
 */
class CashAccount extends Model
{
    protected $table = 'cash_accounts';

    protected $fillable = [
        'name', 'code', 'type', 'provider', 'account_number',
        'branch_id', 'opening_balance', 'opening_date', 'current_balance',
        'is_active', 'sort_order', 'color', 'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'opening_date'    => 'date',
        'is_active'       => 'boolean',
        'sort_order'      => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class, 'account_id');
    }

    /**
     * Recompute current_balance from opening_balance + sum of all
     * transaction impacts. Called after any txn write; also exposed
     * as a manual "resync" action for accounting safety.
     */
    public function recomputeBalance(): void
    {
        $impact = (float) $this->transactions()
            ->selectRaw("
                SUM(CASE WHEN direction = 'in'  THEN amount ELSE 0 END) -
                SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) -
                SUM(charge) AS impact
            ")
            ->value('impact');

        $this->current_balance = round((float) $this->opening_balance + $impact, 2);
        $this->saveQuietly();
    }

    /**
     * Closing balance as of a given date — used by the daily fund report.
     * Same math as recomputeBalance() but bounded to a date.
     */
    public function closingBalanceAt(\Carbon\Carbon $endOfDay): float
    {
        $impact = (float) $this->transactions()
            ->where('transacted_at', '<=', $endOfDay)
            ->selectRaw("
                SUM(CASE WHEN direction = 'in'  THEN amount ELSE 0 END) -
                SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) -
                SUM(charge) AS impact
            ")
            ->value('impact');

        return round((float) $this->opening_balance + $impact, 2);
    }

    /** Active accounts in the viewer's branch scope (HQ-wide rows always visible). */
    public static function visibleTo(?int $branchId)
    {
        return self::query()
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** Pretty label for dropdowns + reports. */
    public function getLabelAttribute(): string
    {
        $bits = [$this->name];
        if ($this->account_number) $bits[] = '· ' . $this->account_number;
        return implode(' ', $bits);
    }
}
