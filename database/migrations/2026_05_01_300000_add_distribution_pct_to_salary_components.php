<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 6.7 — Gross-driven salary distribution.
 *
 * Adds `default_pct` to `salary_components` so each allowance component
 * carries its share of gross salary. HR enters one Gross figure on the
 * employee form, hits Distribute, and the line items fill themselves
 * per these percentages.
 *
 * BD-standard split seeded on existing components:
 *   Basic              60%
 *   House Rent         30%
 *   Medical Allowance   5%   (added below — was missing from seed)
 *   Transport           5%
 *   ---------------------- 100%
 *   Food / Mobile / Service Charge / General — kept at 0% so they're
 *   manual-only line items (festival bonuses, top-ups, etc.).
 *
 * Deductions are never auto-distributed (Income Tax, PF, Advance Recovery
 * compute differently — by slab or fixed amount). default_pct stays NULL
 * for type='deduction' rows.
 *
 * Editable from /admin/salary-components — Master Admin can re-tune the
 * split (e.g. Basic 50 / HR 25 / Med 15 / Transport 10) and re-distribute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_components', 'default_pct')) {
                // Nullable — leaves existing rows untouched until backfilled below.
                $table->decimal('default_pct', 5, 2)->nullable()->after('is_taxable');
            }
        });

        // Backfill BD-standard percentages on the existing four components.
        $defaults = [
            'Basic'               => 60,
            'House Rent'          => 30,
            'Transport Allowance' => 5,
        ];
        foreach ($defaults as $name => $pct) {
            DB::table('salary_components')
                ->where('name', $name)
                ->update(['default_pct' => $pct, 'updated_at' => now()]);
        }

        // Add the missing Medical Allowance row at 5% (only if it doesn't
        // already exist — re-runs of this migration on staging shouldn't
        // double-insert).
        $exists = DB::table('salary_components')->where('name', 'Medical Allowance')->exists();
        if (!$exists) {
            DB::table('salary_components')->insert([
                'name'        => 'Medical Allowance',
                'type'        => 'allowance',
                'is_taxable'  => false,
                'is_active'   => true,
                'sort_order'  => 25, // between House Rent (20) and Transport (30) in the seed
                'default_pct' => 5,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Pull the Medical row back out (ignore if absent).
        DB::table('salary_components')
            ->where('name', 'Medical Allowance')
            ->where('default_pct', 5)
            ->delete();

        Schema::table('salary_components', function (Blueprint $table) {
            if (Schema::hasColumn('salary_components', 'default_pct')) {
                $table->dropColumn('default_pct');
            }
        });
    }
};
