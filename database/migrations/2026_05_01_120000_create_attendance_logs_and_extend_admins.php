<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 1 — Attendance ledger + employee profile extension.
 *
 * `admins` doubles as the employee directory (it already holds the
 * full restaurant staff: master admin, branch managers, waiters,
 * chefs). We add HR-specific columns rather than introducing a new
 * `employees` table so we don't have to keep two tables in sync.
 *
 * `attendance_logs` is one row per "clock in" event. The row stays
 * open (clock_out_at = null) until the staff member clocks out —
 * either manually, automatically when their shift closes, or on
 * end-of-day auto-close.
 *
 * `method` distinguishes how the row was created so reports can
 * surface staff who never clocked in (employer absence) vs staff
 * with stuck-open rows (forgot to clock out, system failure):
 *   manual       — admin/staff hit the clock-in/out button
 *   shift_open   — auto-created when a cashier opens a shift
 *   shift_close  — auto-created when a cashier closes a shift
 *                  (only used for retroactive backfill scenarios)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->date('joining_date')->nullable()->after('status');
            $table->string('designation', 100)->nullable()->after('joining_date');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])
                ->default('full_time')->after('designation');
            $table->decimal('salary_basic', 12, 2)->default(0)->after('employment_type');
            $table->decimal('salary_allowance', 12, 2)->default(0)->after('salary_basic');
            $table->string('emergency_contact_name', 120)->nullable()->after('salary_allowance');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name');
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable();
            // Cashier shift the attendance is tied to — auto-set when
            // the row was created from a shift open. Lets payroll
            // attribute hours to the same window the till was open.
            $table->unsignedBigInteger('shift_id')->nullable()->index();
            $table->enum('method', ['manual', 'shift_open', 'shift_close'])->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
            // Common report query: "today's attendance for this branch".
            $table->index(['branch_id', 'clock_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn([
                'joining_date',
                'designation',
                'employment_type',
                'salary_basic',
                'salary_allowance',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);
        });
    }
};
