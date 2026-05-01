<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 4.1 — Named salary components (Basic, House Rent,
 * Transport, Tax, PF, Advance Recovery, etc.) replace the flat
 * `salary_basic` + `salary_allowance` columns on admins.
 *
 * Old columns stay in schema for backward-compat (and a one-time
 * backfill below seeds existing values into the new line table).
 * They're no longer written by the employee form — line items are
 * the source of truth from this point.
 *
 * Backfill rules:
 *   admins.salary_basic     > 0  → admin_salary_lines: Basic
 *   admins.salary_allowance > 0  → admin_salary_lines: General Allowance
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            // 'allowance' adds to gross; 'deduction' subtracts.
            $table->enum('type', ['allowance', 'deduction']);
            // is_taxable surfaces in pay slips when income tax math
            // ships in a later phase. Restaurant operators can ignore
            // it for now.
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('type');
        });

        Schema::create('admin_salary_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->index();
            $table->unsignedBigInteger('component_id')->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            // One amount per (admin, component) — re-saving the form
            // overwrites rather than duplicates.
            $table->unique(['admin_id', 'component_id'], 'asl_admin_component_unique');
        });

        // Seed components — common Bangladesh restaurant payroll set.
        // sort_order matters: Basic always first, then allowances,
        // then deductions; pay slips render in this order.
        $seed = [
            // allowances
            ['Basic',                'allowance', true,  10],
            ['House Rent',           'allowance', false, 20],
            ['Transport Allowance',  'allowance', false, 30],
            ['Food Allowance',       'allowance', false, 40],
            ['Mobile Allowance',     'allowance', false, 50],
            ['Service Charge',       'allowance', true,  60],
            ['General Allowance',    'allowance', true,  90], // catch-all for legacy backfill
            // deductions
            ['Income Tax',           'deduction', false, 110],
            ['Provident Fund',       'deduction', false, 120],
            ['Advance Recovery',     'deduction', false, 130],
            ['Other Deduction',      'deduction', false, 190],
        ];
        $now = now();
        foreach ($seed as [$name, $type, $taxable, $order]) {
            DB::table('salary_components')->insert([
                'name'        => $name,
                'type'        => $type,
                'is_taxable'  => $taxable,
                'is_active'   => true,
                'sort_order'  => $order,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // Backfill from flat columns. Every existing admin with non-zero
        // values gets a Basic / General Allowance line so their payroll
        // doesn't suddenly drop to zero on the next page load.
        $basicId = DB::table('salary_components')->where('name', 'Basic')->value('id');
        $genId   = DB::table('salary_components')->where('name', 'General Allowance')->value('id');

        if ($basicId) {
            $rows = DB::table('admins')
                ->whereNotNull('salary_basic')
                ->where('salary_basic', '>', 0)
                ->get(['id', 'salary_basic']);
            foreach ($rows as $r) {
                DB::table('admin_salary_lines')->insert([
                    'admin_id'     => $r->id,
                    'component_id' => $basicId,
                    'amount'       => $r->salary_basic,
                    'notes'        => 'Migrated from admins.salary_basic',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
        if ($genId) {
            $rows = DB::table('admins')
                ->whereNotNull('salary_allowance')
                ->where('salary_allowance', '>', 0)
                ->get(['id', 'salary_allowance']);
            foreach ($rows as $r) {
                DB::table('admin_salary_lines')->insert([
                    'admin_id'     => $r->id,
                    'component_id' => $genId,
                    'amount'       => $r->salary_allowance,
                    'notes'        => 'Migrated from admins.salary_allowance',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_salary_lines');
        Schema::dropIfExists('salary_components');
    }
};
