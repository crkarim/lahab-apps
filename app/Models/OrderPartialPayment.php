<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPartialPayment extends Model
{
    use HasFactory;

    protected $casts = [
        'order_id'    => 'integer',
        'handover_id' => 'integer',
        'paid_amount' => 'float',
        'due_amount'  => 'float',
    ];

    protected $fillable = [
        'order_id',
        'paid_with',
        'paid_amount',
        'due_amount',
        // Set when the row's physical money has been handed to the
        // cashier. NULL forever for card / bKash / gateway methods —
        // those don't flow through the cashier's drawer.
        'handover_id',
    ];

    public function handover(): BelongsTo
    {
        return $this->belongsTo(CashHandover::class, 'handover_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Model\Order::class, 'order_id');
    }
}
