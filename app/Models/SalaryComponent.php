<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalogue of pay slip line items — Basic, House Rent, Tax, etc.
 * Type splits the catalogue into "allowance" (added to gross) and
 * "deduction" (subtracted to net). Operators can extend via the
 * components admin (later phase) without touching code.
 */
class SalaryComponent extends Model
{
    protected $table = 'salary_components';

    protected $fillable = [
        'name',
        'type',
        'is_taxable',
        'is_active',
        'sort_order',
        'default_pct',
    ];

    protected $casts = [
        'is_taxable'  => 'boolean',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
        'default_pct' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AdminSalaryLine::class, 'component_id');
    }

    /**
     * Sum of default_pct across active allowance components. Used by the
     * settings page to flag a misconfigured split (warns when != 100).
     * Deductions never contribute — they're always manual.
     */
    public static function distributionTotal(): float
    {
        return (float) self::query()
            ->where('type', 'allowance')
            ->where('is_active', true)
            ->sum('default_pct');
    }

    /** Active allowance components, ordered for the form + pay slip. */
    public static function activeAllowances()
    {
        return self::query()->where('type', 'allowance')->where('is_active', true)->orderBy('sort_order')->get();
    }

    /** Active deduction components. */
    public static function activeDeductions()
    {
        return self::query()->where('type', 'deduction')->where('is_active', true)->orderBy('sort_order')->get();
    }
}
