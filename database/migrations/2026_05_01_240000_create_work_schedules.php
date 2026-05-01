<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 5.1 — Per-employee weekly work schedule.
 *
 * One row per (employee, day_of_week). day_of_week uses MySQL DAYOFWEEK
 * convention: 1 = Sunday, 2 = Monday, ..., 7 = Saturday — convenient
 * because Carbon's `dayOfWeekIso` and PHP's `date("w")` translate
 * cleanly.
 *
 * BD Labour Act 2006 framing for our defaults:
 *   - Sec 100: max 8 paid hours / day (we treat 9 hrs on-site as
 *     8 paid + 1 unpaid lunch).
 *   - Sec 102: max 48 paid hours / week.
 *   - Sec 103: at least one weekly day off (we default Friday off
 *     for the BD-Muslim-majority context; admin can override).
 *   - Sec 108: overtime at 2× ordinary wage — surfaced on attendance
 *     rows as a dedicated minutes count so payroll can multiply.
 *
 * `grace_minutes` is the late-arrival tolerance — if clock_in_at is
 * within `grace_minutes` of `shift_start`, we don't flag the row as
 * late. Default 10 minutes (industry-standard for floor staff).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->index();
            // 1=Sun, 2=Mon, ..., 7=Sat (matches PHP date("w") + 1, MySQL DAYOFWEEK).
            $table->tinyInteger('day_of_week')->unsigned();
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->boolean('is_off_day')->default(false);
            $table->integer('break_minutes')->default(60);
            $table->integer('grace_minutes')->default(10);
            $table->timestamps();
            // One schedule row per (employee, day) — re-saving overwrites.
            $table->unique(['admin_id', 'day_of_week'], 'work_schedule_admin_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
