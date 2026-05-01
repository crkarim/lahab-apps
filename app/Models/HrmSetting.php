<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Tunable HR settings — single source of truth for compliance numbers
 * (gratuity, OT, probation, working hours, weekly off-day, tip share).
 *
 * Read via `HrmSetting::get('ot_multiplier', 2.0)` from PayrollSummariser
 * etc. Cached for 60s so a hot payroll loop doesn't hammer the DB.
 *
 * The admin UI at /admin/hrm-settings groups rows by `group` and renders
 * the right input based on `type` (string|int|decimal|bool|enum). help_text
 * is shown beside each field with a BD Labour Act citation.
 */
class HrmSetting extends Model
{
    protected $table = 'hrm_settings';

    protected $fillable = [
        'key', 'value', 'type', 'group',
        'label', 'help_text', 'options', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /** Cached typed lookup by key. Falls back to $default. */
    public static function get(string $key, $default = null)
    {
        $cached = Cache::remember('hrm_settings.all', 60, function () {
            return self::query()->get(['key', 'value', 'type'])
                ->mapWithKeys(fn ($r) => [$r->key => self::cast($r->value, $r->type)])
                ->toArray();
        });
        return $cached[$key] ?? $default;
    }

    /** Set + invalidate the cache. Used by HrmSettingController@update. */
    public static function set(string $key, $value): void
    {
        $row = self::where('key', $key)->first();
        if (!$row) return;
        $row->value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        $row->save();
        Cache::forget('hrm_settings.all');
    }

    private static function cast($value, string $type)
    {
        if ($value === null) return null;
        return match ($type) {
            'int'     => (int) $value,
            'decimal' => (float) $value,
            'bool'    => (bool) (int) $value,
            default   => $value, // string + enum
        };
    }

    /** Decoded options array for enum settings. Empty array otherwise. */
    public function optionsList(): array
    {
        if ($this->type !== 'enum' || !$this->options) return [];
        $decoded = json_decode($this->options, true);
        return is_array($decoded) ? $decoded : [];
    }
}
