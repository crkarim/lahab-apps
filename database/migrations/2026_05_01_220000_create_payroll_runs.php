<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 4.3 — Payroll Runs.
 *
 * A run is one period (e.g. April 2026) for one branch. It starts
 * as `draft` (live preview, no commitment), gets `locked` (snapshots
 * payslips + deducts advance balances), then optionally `paid`.
 *
 * `payslips` is the snapshot table — one row per (run, employee).
 * Captures the FROZEN values at lock time so the slip remains
 * historically accurate even if salary structure / advances /
 * attendance change later. line_items_json holds the named
 * components used to render the slip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->date('period_from');
            $table->date('period_to');
            $table->enum('status', ['draft', 'locked', 'paid'])->default('draft')->index();

            $table->decimal('total_gross',      14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_advances',   14, 2)->default(0);
            $table->decimal('total_tips',       14, 2)->default(0);
            $table->decimal('total_net',        14, 2)->default(0);

            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('locked_by_admin_id')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'period_from']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('admin_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            // Attendance snapshot
            $table->integer('days_clocked')->default(0);
            $table->integer('calendar_days')->default(0);
            $table->integer('attendance_minutes')->default(0);

            // Money snapshot
            $table->decimal('prorated_basic',     12, 2)->default(0);
            $table->decimal('prorated_allowance', 12, 2)->default(0);
            $table->decimal('prorated_deduction', 12, 2)->default(0);
            $table->decimal('tip_share',          12, 2)->default(0);
            $table->decimal('advance_recovery',   12, 2)->default(0);
            $table->decimal('gross',              12, 2)->default(0);
            $table->decimal('net',                12, 2)->default(0);

            // Frozen-at-lock JSON blobs — pay slip rendering reads
            // from these so the slip stays historically faithful even
            // if catalogue / employee profile change later.
            $table->json('line_items_json')->nullable();
            $table->json('employee_snapshot_json')->nullable();

            // Per-payslip paid flag — some employees get paid before
            // others (e.g. cash today, bank transfer tomorrow).
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_method', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'admin_id'], 'payslips_run_admin_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
    }
};
