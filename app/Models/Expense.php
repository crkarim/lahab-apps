<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One supplier bill / direct purchase. Header carries totals + status;
 * line items live in expense_lines; payments in expense_payments.
 *
 * Status lifecycle:
 *   pending  → bill recorded, no payment yet
 *   partial  → some payments posted, not all
 *   paid     → fully paid
 *   cancelled → void (no ledger impact, balance excluded)
 *   draft    → not yet committed (future use)
 *
 * `paid_amount` is denormalised from sum(expense_payments.amount) so
 * status reconciliation stays cheap.
 */
class Expense extends Model
{
    protected $table = 'expenses';

    protected $fillable = [
        'expense_no', 'supplier_id', 'category_id', 'branch_id',
        'bill_no', 'bill_date', 'due_date',
        'subtotal', 'vat_amount', 'tax_amount', 'discount', 'total',
        'paid_amount', 'status', 'description', 'attachment',
        'recorded_by_admin_id',
    ];

    protected $casts = [
        'bill_date'   => 'date',
        'due_date'    => 'date',
        'subtotal'    => 'decimal:2',
        'vat_amount'  => 'decimal:2',
        'tax_amount'  => 'decimal:2',
        'discount'    => 'decimal:2',
        'total'       => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class, 'expense_id')->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class, 'expense_id')->orderByDesc('paid_at');
    }

    public function balanceDue(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Recompute paid_amount from payments + flip status if needed.
     * Called from controller after every payment / line / total change.
     */
    public function recompute(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $total = (float) $this->total;
        $this->paid_amount = round($paid, 2);

        if ($this->status !== 'cancelled' && $this->status !== 'draft') {
            if (abs($paid - $total) < 0.005) {
                $this->status = 'paid';
            } elseif ($paid > 0.005) {
                $this->status = 'partial';
            } else {
                $this->status = 'pending';
            }
        }
        $this->saveQuietly();

        if ($this->supplier_id) {
            $this->supplier?->recomputeBalance();
        }
    }

    public static function nextExpenseNo(): string
    {
        $year = now()->format('Y');
        $prefix = 'EXP-' . $year . '-';
        $last = self::where('expense_no', 'like', $prefix . '%')
            ->orderByDesc('id')->value('expense_no');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
