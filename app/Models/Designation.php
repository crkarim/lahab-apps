<?php

namespace App\Models;

use App\Model\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One job title — Head Chef, Waiter, Captain, Cashier...
 *
 * `default_basic` is a pay-grade hint shown when the title is picked
 * on the employee form so HR remembers the going rate. The actual
 * salary still lives in admin_salary_lines; nothing reads default_basic
 * once an employee is saved.
 *
 * department_id is nullable so a designation can be cross-department
 * (e.g. "Trainee" applies anywhere) — the employee form will offer all
 * designations regardless of their dept FK.
 */
class Designation extends Model
{
    protected $table = 'designations';

    protected $fillable = [
        'name', 'code', 'department_id',
        'default_basic', 'grade',
        'is_active', 'sort_order', 'notes',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'sort_order'    => 'integer',
        'default_basic' => 'decimal:2',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Admin::class, 'designation_id');
    }

    public static function active()
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
