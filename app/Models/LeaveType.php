<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Leave catalogue. Operators can extend later via a settings page;
 * the migration seeds the BD Labour Act standard set.
 */
class LeaveType extends Model
{
    protected $table = 'leave_types';

    protected $fillable = [
        'name',
        'code',
        'days_per_year',
        'is_paid',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_paid'       => 'boolean',
        'is_active'     => 'boolean',
        'days_per_year' => 'integer',
        'sort_order'    => 'integer',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'leave_type_id');
    }

    public static function active()
    {
        return self::query()->where('is_active', true)->orderBy('sort_order')->get();
    }
}
