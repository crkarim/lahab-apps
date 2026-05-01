<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 5.2 — Leave management.
 *
 * `leave_types` is the catalogue (Casual, Sick, Annual, Festival,
 * Maternity, Unpaid). `days_per_year` seeds the default annual
 * entitlement; balance is computed as
 *   days_per_year − Σ approved request days this calendar year.
 *
 * `leave_requests` is the workflow: pending → approved/rejected/
 * cancelled. `days` is denormalised at save time for fast balance
 * math without re-deriving from the date range.
 *
 * BD Labour Act 2006 entitlements seeded:
 *   Sec 115 — Casual leave: 10 days/yr
 *   Sec 116 — Sick leave:   14 days/yr
 *   Sec 117 — Annual leave: ~20 days/yr (1 day per 18 worked days)
 *   Sec 118 — Festival:     11 days/yr
 *   Sec 46-50 — Maternity:  112 days (16 weeks)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 32)->unique(); // 'casual', 'sick', etc.
            $table->integer('days_per_year')->default(0);
            $table->boolean('is_paid')->default(true);
            $table->string('color', 16)->default('#6A6A70'); // for UI pills
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->index();
            $table->unsignedBigInteger('leave_type_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->date('from_date');
            $table->date('to_date');
            // Inclusive day count — denormalised at request time so
            // balance math stays a single sum() with no per-row date
            // arithmetic.
            $table->integer('days')->default(1);

            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending')->index();

            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();
            $table->index(['admin_id', 'status']);
            $table->index(['admin_id', 'from_date']);
        });

        // Seed BD Labour Act standard leave types.
        $now = now();
        $seed = [
            ['name' => 'Casual',    'code' => 'casual',    'days' => 10,  'paid' => true,  'color' => '#4794FF', 'order' => 10],
            ['name' => 'Sick',      'code' => 'sick',      'days' => 14,  'paid' => true,  'color' => '#E84D4F', 'order' => 20],
            ['name' => 'Annual',    'code' => 'annual',    'days' => 20,  'paid' => true,  'color' => '#1E8E3E', 'order' => 30],
            ['name' => 'Festival',  'code' => 'festival',  'days' => 11,  'paid' => true,  'color' => '#E67E22', 'order' => 40],
            ['name' => 'Maternity', 'code' => 'maternity', 'days' => 112, 'paid' => true,  'color' => '#C548A8', 'order' => 50],
            ['name' => 'Unpaid',    'code' => 'unpaid',    'days' => 0,   'paid' => false, 'color' => '#6A6A70', 'order' => 90],
        ];
        foreach ($seed as $t) {
            DB::table('leave_types')->insert([
                'name'          => $t['name'],
                'code'          => $t['code'],
                'days_per_year' => $t['days'],
                'is_paid'       => $t['paid'],
                'color'         => $t['color'],
                'is_active'     => true,
                'sort_order'    => $t['order'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
    }
};
