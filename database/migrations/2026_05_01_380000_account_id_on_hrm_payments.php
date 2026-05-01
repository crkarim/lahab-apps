<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8.5c — Specific cash account on HRM outflows.
 *
 * `salary_advances.source_account_id`
 *   Where the cash for the advance came from (HQ Safe, Branch Till,
 *   bKash Owner, etc.). Required for posting an OUT row to the ledger
 *   on advance store. Manual recovery posts an IN row to the same or a
 *   chosen account. Legacy rows stay null and skip the auto-post.
 *
 * `payslips.paid_from_account_id`
 *   Where the payslip's net was paid from when HR clicked Mark Paid.
 *   Posts an OUT row tagged ref_type='payslip' to that account.
 *
 * Both nullable + indexed so existing rows aren't disturbed; the auto-
 * post only fires when the operator actually picked an account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_advances', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_advances', 'source_account_id')) {
                $table->unsignedBigInteger('source_account_id')->nullable()->after('balance')->index();
            }
        });

        Schema::table('payslips', function (Blueprint $table) {
            if (!Schema::hasColumn('payslips', 'paid_from_account_id')) {
                $table->unsignedBigInteger('paid_from_account_id')->nullable()->after('paid_by_admin_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            if (Schema::hasColumn('payslips', 'paid_from_account_id')) {
                $table->dropIndex(['paid_from_account_id']);
                $table->dropColumn('paid_from_account_id');
            }
        });
        Schema::table('salary_advances', function (Blueprint $table) {
            if (Schema::hasColumn('salary_advances', 'source_account_id')) {
                $table->dropIndex(['source_account_id']);
                $table->dropColumn('source_account_id');
            }
        });
    }
};
