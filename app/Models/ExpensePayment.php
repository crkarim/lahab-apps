<?php

namespace App\Models;

use App\Model\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment row against a bill. Each payment auto-posts a single
 * OUT row to the chosen cash account via PostExpensePaymentToLedger.
 */
class ExpensePayment extends Model
{
    protected $table = 'expense_payments';

    protected $fillable = [
        'payment_no', 'expense_id', 'cash_account_id',
        'amount', 'method', 'reference', 'paid_at',
        'paid_by_admin_id', 'notes',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'paid_by_admin_id');
    }

    public static function nextPaymentNo(): string
    {
        $year = now()->format('Y');
        $prefix = 'PAY-' . $year . '-';
        $last = self::where('payment_no', 'like', $prefix . '%')
            ->orderByDesc('id')->value('payment_no');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
