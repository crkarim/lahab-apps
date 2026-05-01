<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HrmSetting;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * HRM Phase 6 — Tunable settings page.
 *
 * Master Admin only. Renders all rows grouped by `group` (gratuity,
 * overtime, probation, working_time, tips). Save is bulk: every visible
 * key gets posted, we update only the rows that changed, and bust the
 * HrmSetting::get() cache so payroll math sees the new numbers
 * immediately.
 *
 * The page is intentionally small + opinionated — not a generic
 * key/value editor. Each setting has a label + help text (BD Labour Act
 * citation where applicable) so HR doesn't need the act open.
 */
class HrmSettingController extends Controller
{
    public function index(Request $request): Renderable
    {
        $rows = HrmSetting::query()
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get();

        $groups = $rows->groupBy('group');

        // Pretty group titles in render order.
        $groupTitles = [
            'gratuity'     => 'Gratuity',
            'overtime'     => 'Overtime',
            'probation'    => 'Probation',
            'working_time' => 'Working time',
            'tips'         => 'Tips & service charge',
            'general'      => 'General',
        ];

        return view('admin-views.hrm-settings.index', [
            'groups'      => $groups,
            'groupTitles' => $groupTitles,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $payload = (array) $request->input('settings', []);
        if (empty($payload)) {
            return back()->with('error', 'Nothing to update.');
        }

        $updated = 0;
        $warnings = [];

        foreach ($payload as $key => $value) {
            $row = HrmSetting::where('key', $key)->first();
            if (!$row) continue;

            // Type-validate.
            $validated = $this->validateOne($row, $value);
            if ($validated === null) continue;

            if ((string) $row->value !== (string) $validated) {
                $row->value = (string) $validated;
                $row->save();
                $updated++;
            }

            // Soft compliance warnings — don't block, but flag.
            if ($row->key === 'ot_multiplier' && (float) $validated < 2.0) {
                $warnings[] = 'OT multiplier ' . $validated . '× is below BD Labour Act Sec 108 minimum (2.0×).';
            }
            if ($row->key === 'gratuity_min_years' && (int) $validated > 5) {
                $warnings[] = 'Gratuity threshold > 5 yrs is stricter than BD statute (Sec 2(10) requires gratuity from 5 yrs).';
            }
        }

        Cache::forget('hrm_settings.all');

        $msg = $updated . ' setting(s) updated.';
        if ($warnings) {
            $msg .= ' Compliance notes: ' . implode(' / ', $warnings);
        }
        return back()->with($warnings ? 'error' : 'success', $msg);
    }

    private function validateOne(HrmSetting $row, $value)
    {
        return match ($row->type) {
            'int'     => is_numeric($value) ? (int) $value : null,
            'decimal' => is_numeric($value) ? (float) $value : null,
            'bool'    => $value ? '1' : '0',
            'enum'    => array_key_exists($value, $row->optionsList()) ? (string) $value : null,
            default   => is_string($value) ? trim($value) : null,
        };
    }
}
