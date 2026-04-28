<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Print-failure escalation surface. When the network-printer push fails
 * during Send-to-Kitchen, we stamp the order so the admin panel can pop
 * an urgent acknowledgement modal until counter staff actions it
 * (retry print, or "mark handled" because they hand-wrote a paper KOT).
 *
 * Single-table approach (no separate `print_failures` table) because:
 *   - one failure per order is enough — retries don't compound rows
 *   - keeps the pending-list query fast (`WHERE print_failure_at NOT NULL
 *     AND print_failure_handled_at IS NULL`)
 *   - downstream reports can audit failures by joining nothing extra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('print_failure_at')->nullable()->after('kot_print_count')->index();
            $table->string('print_failure_reason', 255)->nullable()->after('print_failure_at');
            $table->timestamp('print_failure_handled_at')->nullable()->after('print_failure_reason');
            $table->unsignedBigInteger('print_failure_handled_by')->nullable()->after('print_failure_handled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['print_failure_at']);
            $table->dropColumn([
                'print_failure_at',
                'print_failure_reason',
                'print_failure_handled_at',
                'print_failure_handled_by',
            ]);
        });
    }
};
