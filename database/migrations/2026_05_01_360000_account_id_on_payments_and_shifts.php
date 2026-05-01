<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8.5 — Specific cash account on payment-bearing rows.
 *
 * `order_partial_payments.cash_account_id`
 *   The exact cash account each partial payment lands in. Cashier picks
 *   it at POS / checkout time. PostOrderPaymentToLedger now uses this
 *   verbatim instead of fuzzy-matching by method string.
 *
 * `shifts.cash_account_id`
 *   The till the shift opens against. Required for new shifts so a
 *   close-out variance can post to the right account. Existing legacy
 *   rows stay null and skip the ledger post on close.
 *
 * `shifts.variance_reason`
 *   Mandatory note when actual_cash != expected_cash on close. Stored
 *   here AND copied into the variance ledger row's description so the
 *   audit trail survives a row deletion later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_partial_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('order_partial_payments', 'cash_account_id')) {
                $table->unsignedBigInteger('cash_account_id')->nullable()->after('paid_with')->index();
            }
        });

        Schema::table('shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('shifts', 'cash_account_id')) {
                $table->unsignedBigInteger('cash_account_id')->nullable()->after('branch_id')->index();
            }
            if (!Schema::hasColumn('shifts', 'variance_reason')) {
                $table->text('variance_reason')->nullable()->after('variance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            if (Schema::hasColumn('shifts', 'variance_reason')) $table->dropColumn('variance_reason');
            if (Schema::hasColumn('shifts', 'cash_account_id')) {
                $table->dropIndex(['cash_account_id']);
                $table->dropColumn('cash_account_id');
            }
        });
        Schema::table('order_partial_payments', function (Blueprint $table) {
            if (Schema::hasColumn('order_partial_payments', 'cash_account_id')) {
                $table->dropIndex(['cash_account_id']);
                $table->dropColumn('cash_account_id');
            }
        });
    }
};
