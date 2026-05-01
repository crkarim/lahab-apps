<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 4.2 — Salary advances / loans.
 *
 * One row per advance the employer gave to an employee. The full
 * `amount` is reduced by `recovery_per_run` each time a payroll run
 * is locked (Phase 4.3 wires the actual deduction); `balance` tracks
 * what's left to recover.
 *
 * Status flow:
 *   active     — advance still being recovered
 *   recovered  — balance hit zero; locked to the run that closed it
 *   cancelled  — written off / refunded, no further deductions
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->decimal('recovery_per_run', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->date('taken_at');
            $table->string('reason', 255)->nullable();
            $table->enum('status', ['active', 'recovered', 'cancelled'])->default('active')->index();
            $table->unsignedBigInteger('recorded_by_admin_id')->nullable();
            $table->unsignedBigInteger('recovered_by_run_id')->nullable(); // FK to payroll_runs (Phase 4.3)
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['admin_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_advances');
    }
};
