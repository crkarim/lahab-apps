<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Two-level taxonomy for expense categorisation. Top-level rows have
 * parent_id NULL; children point at their parent (e.g. Utilities >
 * Electricity / Water / Gas).
 *
 * Editable from /admin/expense-categories — operators can rename,
 * recolor, add new sub-categories, deactivate. Bills FK directly to
 * any level (line items can override per-row).
 */
class ExpenseCategory extends Model
{
    protected $table = 'expense_categories';

    protected $fillable = [
        'name', 'code', 'parent_id',
        'color', 'is_active', 'sort_order', 'description',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    /** Pretty display label including parent path: "Utilities → Electricity". */
    public function getLabelAttribute(): string
    {
        return $this->parent_id && $this->parent
            ? $this->parent->name . ' → ' . $this->name
            : $this->name;
    }

    public static function activeTree()
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');
    }
}
