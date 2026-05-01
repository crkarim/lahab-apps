<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\HasApiTokens;

class Admin extends Authenticatable
{
    // HasApiTokens lets the waiter-app API issue Passport tokens for staff
    // (employees) — separate guard `waiter_api`, used in routes/api/v1/api.php.
    use HasApiTokens, Notifiable;

    protected $fillable = ['admin_role_id', 'branch_id'];

    // HR fields (Phase 2 of the HRM module) cast so the edit blade can
    // call `optional($employee->joining_date)->format('Y-m-d')` and
    // payroll math has real numbers, not strings.
    protected $casts = [
        'joining_date'     => 'date',
        'salary_basic'     => 'decimal:2',
        'salary_allowance' => 'decimal:2',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }

    /** Optional branch assignment — NULL means HQ/global admin. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /** HRM Phase 6 — department / designation / reports-to chain. */
    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }

    public function designationRef(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Designation::class, 'designation_id');
    }

    /** The manager this employee reports to. NULL = no direct manager set. */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to_admin_id');
    }

    /** Direct reports — drives the org chart + leave approval inbox. */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'reports_to_admin_id');
    }

    /** Salary structure — one line per pay component (Basic, HR, Tax, ...). */
    public function salaryLines()
    {
        return $this->hasMany(\App\Models\AdminSalaryLine::class, 'admin_id');
    }

    /** Sum of allowance lines (gross salary before tip share + proration). */
    public function salaryAllowanceTotal(): float
    {
        return (float) $this->salaryLines()
            ->whereHas('component', fn ($q) => $q->where('type', 'allowance'))
            ->sum('amount');
    }

    /** Sum of deduction lines. */
    public function salaryDeductionTotal(): float
    {
        return (float) $this->salaryLines()
            ->whereHas('component', fn ($q) => $q->where('type', 'deduction'))
            ->sum('amount');
    }

    public function getImageFullPathAttribute(): string
    {
        $image = $this->image ?? null;
        $path = asset('public/assets/admin/img/400x400/img2.jpg');

        if (!is_null($image) && Storage::disk('public')->exists('admin/' . $image)) {
            $path = asset('storage/app/public/admin/' . $image);
        }
        return $path;
    }

    public function getIdentityImageFullPathAttribute()
    {
        $value = $this->identity_image ?? [];
        $imageUrlArray = is_array($value) ? $value : json_decode($value, true);
        // Guarantee an array — empty/null/invalid JSON would otherwise
        // return null and throw `foreach() argument must be of type
        // array|object, null given` when the edit Blade iterates.
        if (!is_array($imageUrlArray)) {
            $imageUrlArray = [];
        }
        foreach ($imageUrlArray as $key => $item) {
            if (Storage::disk('public')->exists('admin/' . $item)) {
                $imageUrlArray[$key] = asset('storage/app/public/admin/'. $item);
            } else {
                $imageUrlArray[$key] = asset('public/assets/admin/img/400x400/img2.jpg');
            }
        }
        return $imageUrlArray;
    }
}
