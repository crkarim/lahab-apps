<?php

namespace App\Models;

use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One vendor — meat / fish / vegetable wholesaler, gas cylinder
 * supplier, fuel station, packaging vendor, etc.
 *
 * `outstanding_balance` is denormalised: sum of expenses.total minus
 * sum of expense_payments.amount across this supplier's bills. Kept
 * fresh by Expense::recomputeSupplierBalance() which fires on every
 * bill / payment write.
 *
 * branch_id null = HQ-wide vendor (used by every branch). Otherwise
 * scoped to one branch (e.g. "Gulshan-only veg supplier").
 */
class Supplier extends Model
{
    protected $table = 'suppliers';

    protected $fillable = [
        'name', 'code', 'contact_person', 'phone', 'email', 'address',
        'bin', 'payment_terms', 'branch_id', 'outstanding_balance',
        'is_active', 'sort_order', 'notes',
    ];

    protected $casts = [
        'outstanding_balance' => 'decimal:2',
        'is_active'           => 'boolean',
        'sort_order'          => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'supplier_id');
    }

    /** Visible-to-this-viewer suppliers. Branch-scoped + HQ-wide. */
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

    /**
     * Recompute outstanding balance = sum(expenses.total)
     *  − sum(expense_payments.amount) across this supplier's non-cancelled
     * bills. Called after every bill / payment write.
     */
    public function recomputeBalance(): void
    {
        $billed = (float) $this->expenses()
            ->whereIn('status', ['pending', 'partial', 'paid'])
            ->sum('total');
        $paid = (float) $this->expenses()
            ->whereIn('status', ['pending', 'partial', 'paid'])
            ->sum('paid_amount');
        $this->outstanding_balance = round($billed - $paid, 2);
        $this->saveQuietly();
    }
}
