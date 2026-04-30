<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier shift sessions — software ledger that scopes a "drawer" to
 * one cashier between Open Shift and Close Shift.
 *
 * The drawer is purely logical right now (no kick-drawer hardware on
 * site). Running balance:
 *   opening_cash
 *     + cash sales taken at this cashier's POS
 *     + cash handovers received from waiters during this shift
 *     - manual cash payouts (refunds, petty cash) recorded against the shift
 *   = expected_cash
 *
 * On close, the cashier counts the till and enters `actual_cash`. The
 * variance + notes are persisted so head office has an audit trail.
 *
 * `orders.shift_id` is added nullable so unflagged historical orders
 * stay untouched and the new shift screen surfaces them as "no shift"
 * rather than rejecting them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('opened_by_admin_id')->index();
            $table->timestamp('opened_at')->useCurrent();
            $table->decimal('opening_cash', 12, 2)->default(0);

            $table->unsignedBigInteger('closed_by_admin_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();

            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->nullable()->after('placed_by_admin_id');
            $table->index('shift_id');
        });

        // Cash handovers attributed to the cashier's open shift at the
        // moment the cashier ack'd them. Lets shift reconciliation
        // include the right slice of waiter-submitted cash.
        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->nullable()->after('cashier_id');
            $table->index('shift_id');
        });

        // Manual cash-out events for variance reconciliation —
        // refund handed over the counter, petty cash for supplies, etc.
        Schema::create('shift_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_id')->index();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->unsignedBigInteger('recorded_by_admin_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_payouts');
        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->dropIndex(['shift_id']);
            $table->dropColumn('shift_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shift_id']);
            $table->dropColumn('shift_id');
        });
        Schema::dropIfExists('shifts');
    }
};
