<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per "waiter submitted cash to cashier" event. Groups the
 * `order_partial_payments` rows whose physical money is being handed
 * over, so the cashier can ack a batch instead of per-order.
 *
 * Status flow:
 *   pending  — waiter created the row; rows are locked but not yet
 *              counted in the drawer
 *   received — cashier ack'd; drawer reconciliation can include them
 *   disputed — operator flagged a mismatch (count vs claim doesn't add up)
 */
class CashHandover extends Model
{
    protected $table = 'cash_handovers';

    protected $fillable = [
        'waiter_id',
        'cashier_id',
        'branch_id',
        'submitted_at',
        'received_at',
        'total_cash',
        'total_tips',
        'status',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'received_at'  => 'datetime',
        'total_cash'   => 'float',
        'total_tips'   => 'float',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPartialPayment::class, 'handover_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'waiter_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'cashier_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Total physical cash on the row. `total_tips` is NOT added —
     * it's already a slice of `total_cash` (the customer paid the tip
     * in cash along with the bill). Tips paid by card never reach the
     * waiter's hand and aren't tracked here at all.
     */
    public function getTotalAttribute(): float
    {
        return (float) $this->total_cash;
    }
}
