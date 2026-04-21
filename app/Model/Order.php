<?php

namespace App\Model;

use App\Models\OfflinePayment;
use App\Models\GuestUser;
use App\Models\OrderChangeAmount;
use App\Models\OrderPartialPayment;
use App\User;
use App\Models\OrderArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $casts = [
        'order_amount' => 'float',
        'coupon_discount_amount' => 'float',
        'total_tax_amount' => 'float',
        'total_add_on_tax' => 'float',
        'delivery_address_id' => 'integer',
        'delivery_man_id' => 'integer',
        'delivery_charge' => 'float',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'kot_sent_at' => 'datetime',
        'delivery_address' => 'array',
        'table_id' => 'integer',
        'number_of_people' => 'integer',
        'table_order_id' => 'integer',
        'is_cutlery_required' => 'integer',
        'bring_change_amount' => 'float',
        'referral_discount' => 'float',
    ];

    protected $fillable = [
        'order_amount',
        'coupon_discount_amount',
        'total_tax_amount',
        'total_add_on_tax',
        'delivery_address_id',
        'delivery_man_id',
        'delivery_charge',
        'user_id',
        'created_at',
        'updated_at',
        'delivery_address',
        'table_id',
        'number_of_people',
        'table_order_id',
        'is_cutlery_required',
        'bring_change_amount',
        'referral_discount',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    /**
     * Is this order's line-up locked against reductions/deletions?
     *
     * Business rule: once the kitchen is actively cooking (status=cooking AND
     * the KOT was sent 5+ minutes ago), existing items can't be removed or
     * have their quantity reduced — the food is on the grill. Staff can still
     * ADD items or INCREASE quantity on existing items (delta fires a
     * supplementary KOT). Everything is permissive in earlier states.
     */
    public function isEditLocked(): bool
    {
        if ($this->order_status !== 'cooking') {
            return false;
        }
        if (!$this->kot_sent_at) {
            return false;
        }
        return $this->kot_sent_at->lte(now()->subMinutes(5));
    }

    public function delivery_man(): BelongsTo
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id')->withCount('orders');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withCount('orders');
    }

    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'placed_by_admin_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withCount('orders');
    }

    public function delivery_address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'delivery_address_id');
    }

    public function customer_delivery_address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'delivery_address_id');
    }

    public function table_order(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class, 'table_order_id', 'id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_id', 'id');
    }

    public function scopePos($query)
    {
        return $query->where('order_type', '=', 'pos');
    }

    public function scopeDineIn($query)
    {
        return $query->where('order_type', '=', 'dine_in');
    }


    public function scopeNotDineIn($query)
    {
        return $query->where('order_type', '!=', 'dine_in');
    }

    public function scopeNotPos($query)
    {
        return $query->where('order_type', '!=', 'pos');
    }

    public function scopeSchedule($query)
    {
        return $query->whereDate('delivery_date', '>', \Carbon\Carbon::now()->format('Y-m-d'));
    }

    public function scopeNotSchedule($query)
    {
        return $query->whereDate('delivery_date', '<=', \Carbon\Carbon::now()->format('Y-m-d'));
    }

    public function scopeEarningReport($query)
    {
        return $query->whereIn('order_status', ['delivered', 'completed']);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(OrderTransaction::class);
    }

    public function order_partial_payments(): HasMany
    {
        return $this->hasMany(OrderPartialPayment::class)->orderBy('id', 'DESC');
    }

    public function offline_payment()
    {
        return $this->hasOne(OfflinePayment::class, 'order_id');
    }

    public function scopePartial($query)
    {
        return $query->whereHas('partial_payment');
    }

    public function guest()
    {
        return $this->belongsTo(GuestUser::class, 'user_id');
    }

    public function deliveryman_review()
    {
        return $this->hasOne(DMReview::class, 'order_id');
    }

    public function order_area()
    {
        return $this->hasOne(OrderArea::class, 'order_id');
    }

    public function order_change_amount()
    {
        return $this->hasOne(OrderChangeAmount::class, 'order_id');
    }
}
