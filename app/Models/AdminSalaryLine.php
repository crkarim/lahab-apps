<?php

namespace App\Models;

use App\Model\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One amount per (employee, salary component). Sum of allowance lines
 * = gross salary; sum of deduction lines = total deductions; net is
 * the difference (plus prorations and tip share — computed in the
 * Payroll module).
 *
 * Unique (admin_id, component_id) at the DB level so re-saving the
 * employee form just overwrites the value rather than stacking
 * duplicates.
 */
class AdminSalaryLine extends Model
{
    protected $table = 'admin_salary_lines';

    protected $fillable = [
        'admin_id',
        'component_id',
        'amount',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'component_id');
    }
}
