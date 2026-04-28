<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }

    /** Optional branch assignment — NULL means HQ/global admin. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
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
        if (is_array($imageUrlArray)) {
            foreach ($imageUrlArray as $key => $item) {
                if (Storage::disk('public')->exists('admin/' . $item)) {
                    $imageUrlArray[$key] = asset('storage/app/public/admin/'. $item) ;
                } else {
                    $imageUrlArray[$key] = asset('public/assets/admin/img/400x400/img2.jpg');
                }
            }
        }
        return $imageUrlArray;
    }
}
