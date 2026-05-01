<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * HRM Phase 6.7 — Salary components catalogue + gross-distribution rules.
 *
 * Master Admin only. The page surfaces every pay-slip line-item the
 * employee form can use, plus each allowance's default_pct (share of
 * gross when "Distribute" is hit on the employee form).
 *
 * Allowance percentages must sum to 100; the page warns when they
 * don't but still saves — different companies use different splits
 * (e.g. excluding bonus components from the auto-distribution and
 * filling them manually).
 *
 * Deductions never carry a default_pct (Income Tax / PF / Advance
 * Recovery compute by slab or per-row, not as a flat % of gross).
 */
class SalaryComponentController extends Controller
{
    public function index(Request $request): Renderable
    {
        $allowances = SalaryComponent::query()
            ->where('type', 'allowance')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $deductions = SalaryComponent::query()
            ->where('type', 'deduction')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $allowanceTotal = (float) $allowances->where('is_active', true)->sum('default_pct');

        return view('admin-views.salary-components.index', [
            'allowances'     => $allowances,
            'deductions'     => $deductions,
            'allowanceTotal' => $allowanceTotal,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:80',
            'type'        => 'required|in:allowance,deduction',
            'default_pct' => 'nullable|numeric|min:0|max:100',
            'is_taxable'  => 'nullable|boolean',
            'sort_order'  => 'nullable|integer',
        ]);

        SalaryComponent::create([
            'name'        => $validated['name'],
            'type'        => $validated['type'],
            // Distribution % is meaningless on deductions — drop it server-side.
            'default_pct' => $validated['type'] === 'allowance' ? ($validated['default_pct'] ?? null) : null,
            'is_taxable'  => (bool) ($validated['is_taxable'] ?? false),
            'is_active'   => true,
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Component created · ' . $validated['name']);
    }

    /**
     * Bulk update from the index page — every visible row is posted at
     * once so HR can re-tune Basic/HR/Med/Transport in one go and see
     * the new sum immediately.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $rows = (array) $request->input('rows', []);
        if (empty($rows)) return back()->with('error', 'Nothing to update.');

        $updated = 0;
        foreach ($rows as $id => $data) {
            $row = SalaryComponent::find((int) $id);
            if (!$row) continue;

            $newName  = trim((string) ($data['name'] ?? $row->name));
            $newPct   = isset($data['default_pct']) && $data['default_pct'] !== ''
                ? max(0, min(100, (float) $data['default_pct']))
                : null;
            $newSort  = (int) ($data['sort_order'] ?? $row->sort_order);
            $newTax   = !empty($data['is_taxable']);
            $newActive= !empty($data['is_active']);

            // Don't allow renaming/deactivating "Basic" via bulk path —
            // payroll math relies on that exact label. Edit it via the
            // dedicated update() endpoint with confirmation if you must.
            if ($row->name === 'Basic') {
                $newName   = 'Basic';
                $newActive = true;
            }

            $row->forceFill([
                'name'        => $newName ?: $row->name,
                'default_pct' => $row->type === 'allowance' ? $newPct : null,
                'sort_order'  => $newSort,
                'is_taxable'  => $newTax,
                'is_active'   => $newActive,
            ])->save();
            $updated++;
        }

        $total = SalaryComponent::distributionTotal();
        $msg   = $updated . ' component(s) updated · allowance distribution total ' . number_format($total, 2) . '%';
        if (abs($total - 100) > 0.01) {
            $msg .= ' (not 100% — gross-distribute will leave a gap or overshoot).';
            return back()->with('error', $msg);
        }
        return back()->with('success', $msg);
    }

    public function destroy(int $id): RedirectResponse
    {
        $row = SalaryComponent::withCount('lines')->find($id);
        if (!$row) return back()->with('error', 'Component not found.');

        if ($row->name === 'Basic') {
            return back()->with('error', 'The "Basic" component is structural — payroll math depends on it. Deactivate instead of delete.');
        }

        if ($row->lines_count > 0) {
            $row->is_active = false;
            $row->save();
            return back()->with('success', $row->name . ' deactivated (in use by ' . $row->lines_count . ' employee line(s)).');
        }

        $row->delete();
        return back()->with('success', 'Component deleted.');
    }
}
