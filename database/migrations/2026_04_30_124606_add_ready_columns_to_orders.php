<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for the kitchen-ready transition (Phase 3a).
 *
 * Workflow: kitchen scans the printed KOT barcode when food is plated.
 * That POSTs to /admin/kitchen/scan, which flips order_status='ready'
 * and stamps these two columns. The waiter app then receives an FCM
 * push (waiter-{branchId} topic) so the placing waiter knows to pick
 * the food up.
 *
 * `order_status='ready'` sits between `cooking` and `completed`. It's
 * still "active" from the cashier's perspective (dine-in orders stay
 * unpaid until checkout) so the active-orders filter doesn't change
 * — `ready` just isn't in the TERMINAL list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('ready_at')->nullable()->after('kot_print_count');
            $table->unsignedBigInteger('ready_by_admin_id')->nullable()->after('ready_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ready_at', 'ready_by_admin_id']);
        });
    }
};
