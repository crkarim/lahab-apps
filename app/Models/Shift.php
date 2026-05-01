<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use App\Model\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per cashier "shift" — the period between Open Drawer and
 * Close Drawer for a single cashier on a single branch. Drives the
 * Day-End Report's per-shift scope and exposes a running expected vs
 * actual cash variance.
 *
 * Cash flows tracked:
 *   in  : opening_cash + cash POS sales + cash handovers received
 *   out : shift_payouts (refunds, petty cash, etc.)
 */
class Shift extends Model
{
    protected $table = 'shifts';

    protected $fillable = [
        'branch_id',
        // Phase 8.5 — the cash account (till) this shift opens against.
        // Variance posts to this account at close.
        'cash_account_id',
        'opened_by_admin_id',
        'opened_at',
        'opening_cash',
        'closed_by_admin_id',
        'closed_at',
        'expected_cash',
        'actual_cash',
        'variance',
        'variance_reason',
        'notes',
        'status',
    ];

    protected $casts = [
        'opened_at'     => 'datetime',
        'closed_at'     => 'datetime',
        'opening_cash'  => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash'   => 'decimal:2',
        'variance'      => 'decimal:2',
    ];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'opened_by_admin_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'closed_by_admin_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'shift_id');
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(CashHandover::class, 'shift_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(ShiftPayout::class, 'shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Recompute the expected cash from live sources. Cheap enough to
     * call on every Close Shift submit; we don't denormalise these
     * subtotals onto the row to avoid drift.
     */
    public function computeExpectedCash(): float
    {
        // POS cash sales that flowed straight into this cashier's till.
        // Only count cash partial-payment rows on this shift's orders.
        $cashSales = (float) \App\Models\OrderPartialPayment::query()
            ->where('paid_with', 'cash')
            ->whereIn('order_id', $this->orders()->pluck('id'))
            ->sum('paid_amount');

        // Cash handovers waiters submitted during this shift and the
        // cashier received in the same window.
        $handoverCash = (float) $this->handovers()
            ->where('status', 'received')
            ->sum('total_cash');

        $payouts = (float) $this->payouts()->sum('amount');

        return (float) $this->opening_cash + $cashSales + $handoverCash - $payouts;
    }
}
