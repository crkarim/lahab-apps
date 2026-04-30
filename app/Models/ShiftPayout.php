<?php

namespace App\Models;

use App\Model\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual cash-out event during a shift — refund handed across the
 * counter, petty cash for a supplier delivery, etc. Reduces the
 * shift's expected_cash so the close-shift count reconciles cleanly.
 */
class ShiftPayout extends Model
{
    protected $table = 'shift_payouts';

    protected $fillable = [
        'shift_id',
        'amount',
        'reason',
        'recorded_by_admin_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }
}
