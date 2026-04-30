<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash handover lifecycle — bridges the gap between waiters who collect
 * physical cash + tips at the table and the cashier who reconciles the
 * drawer at end of shift. One handover row per submission; each row
 * groups the order_partial_payment rows the waiter is handing over via
 * the new `handover_id` foreign key.
 *
 * Card / bKash / Nagad / Rocket payments stay null on `handover_id`
 * because they settle through a gateway — the cashier doesn't physically
 * receive that money, just verifies the gateway report. Only cash + tips
 * flow through this table.
 *
 * Status:
 *   pending  — waiter submitted, cashier hasn't acknowledged yet
 *   received — cashier acknowledged + drawer reconciled
 *   disputed — operator flagged a mismatch, needs HQ review
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('waiter_id');
            $table->unsignedBigInteger('cashier_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('received_at')->nullable();
            $table->decimal('total_cash', 10, 2)->default(0);
            $table->decimal('total_tips', 10, 2)->default(0);
            $table->string('status', 16)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index('waiter_id');
        });

        Schema::table('order_partial_payments', function (Blueprint $table) {
            // Nullable: only cash / tip rows ever populate this. Card +
            // gateway payments stay NULL forever (no physical handover).
            $table->unsignedBigInteger('handover_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('order_partial_payments', function (Blueprint $table) {
            $table->dropIndex(['handover_id']);
            $table->dropColumn('handover_id');
        });
        Schema::dropIfExists('cash_handovers');
    }
};
