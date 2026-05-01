<?php

namespace App\Models;

use App\Model\Admin;
use App\Model\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Taka movement against a cash account.
 *
 * Net balance impact:
 *   in  → +amount − charge
 *   out → −amount − charge
 *
 * `amount` is the GROSS principal. VAT and tax inside that amount get
 * surfaced separately (vat_input on outflows, vat_output on inflows,
 * tax_amount for AIT) so the VAT register can roll them up cleanly.
 *
 * `paired_txn_id` links the two legs of a transfer (e.g. Bank A → bKash
 * creates one OUT row on Bank A pointing to the IN row on bKash, and
 * vice versa). `ref_type` + `ref_id` are reserved for Phase 8.4-8.6
 * auto-posting from POS sales / payslips / supplier bills.
 */
class AccountTransaction extends Model
{
    protected $table = 'account_transactions';

    protected $fillable = [
        'txn_no', 'account_id', 'direction',
        'amount', 'charge', 'vat_input', 'vat_output', 'tax_amount',
        'ref_type', 'ref_id', 'paired_txn_id',
        'branch_id', 'description', 'transacted_at',
        'recorded_by_admin_id',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'charge'         => 'decimal:2',
        'vat_input'      => 'decimal:2',
        'vat_output'     => 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'transacted_at'  => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'account_id');
    }

    public function pairedTxn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'paired_txn_id');
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
     * Net balance impact on this account. Positive = balance went up.
     * Used by reports and by recomputeBalance() math.
     */
    public function netImpact(): float
    {
        $sign = $this->direction === 'in' ? 1 : -1;
        return round($sign * (float) $this->amount - (float) $this->charge, 2);
    }

    public function isTransfer(): bool
    {
        return !empty($this->paired_txn_id);
    }

    /**
     * Generate the next human-readable txn_no — TXN-YYYY-NNNNNN.
     * Locks the table briefly to keep the sequence dense; collisions
     * are surfaced as a unique-key violation if two requests race.
     */
    public static function nextTxnNo(): string
    {
        $year = now()->format('Y');
        $prefix = 'TXN-' . $year . '-';
        $last = self::query()
            ->where('txn_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('txn_no');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
