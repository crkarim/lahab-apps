<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 7a — Salary disbursement fields.
 *
 * `admins` extension:
 *   - payment_method      cash | bank | mobile | cheque
 *   - bank_*              account name/number, branch, routing (BD: 9-digit)
 *   - mobile_provider     bkash | nagad | rocket | upay
 *   - mobile_wallet       11-digit MFS number
 *
 * Each row is optional — operator picks one method per employee. Bank
 * fields filled iff method=bank; mobile fields iff method=mobile.
 *
 * `payslips` extension:
 *   - paid_reference      bank txn / bKash trxID / cheque #, captured at
 *                         the moment HR marks the slip paid
 *   - paid_by_admin_id    who hit the button (audit trail)
 *
 * `paid_method` + `paid_at` already exist on payslips; we keep them.
 *
 * Future phase 7b will use these to generate the bank disbursement CSV
 * (account-by-account totals for batch upload to the company's bank).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (!Schema::hasColumn('admins', 'payment_method')) {
                $table->enum('payment_method', ['cash', 'bank', 'mobile', 'cheque'])
                    ->default('cash')
                    ->after('reports_to_admin_id');
            }
            // Bank block — for method='bank'.
            if (!Schema::hasColumn('admins', 'bank_name')) {
                $table->string('bank_name', 80)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('admins', 'bank_branch')) {
                $table->string('bank_branch', 80)->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('admins', 'bank_account_name')) {
                $table->string('bank_account_name', 120)->nullable()->after('bank_branch');
            }
            if (!Schema::hasColumn('admins', 'bank_account_number')) {
                $table->string('bank_account_number', 40)->nullable()->after('bank_account_name');
            }
            if (!Schema::hasColumn('admins', 'bank_routing_number')) {
                $table->string('bank_routing_number', 20)->nullable()->after('bank_account_number');
            }
            // Mobile financial services block — for method='mobile'.
            if (!Schema::hasColumn('admins', 'mobile_provider')) {
                $table->enum('mobile_provider', ['bkash', 'nagad', 'rocket', 'upay'])
                    ->nullable()
                    ->after('bank_routing_number');
            }
            if (!Schema::hasColumn('admins', 'mobile_wallet_number')) {
                $table->string('mobile_wallet_number', 20)->nullable()->after('mobile_provider');
            }
        });

        Schema::table('payslips', function (Blueprint $table) {
            if (!Schema::hasColumn('payslips', 'paid_reference')) {
                // Free-text — bank txn id, bKash trxID, cheque number, etc.
                $table->string('paid_reference', 80)->nullable()->after('paid_method');
            }
            if (!Schema::hasColumn('payslips', 'paid_by_admin_id')) {
                $table->unsignedBigInteger('paid_by_admin_id')->nullable()->after('paid_reference')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            foreach (['paid_by_admin_id', 'paid_reference'] as $col) {
                if (Schema::hasColumn('payslips', $col)) {
                    if ($col === 'paid_by_admin_id') $table->dropIndex(['paid_by_admin_id']);
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('admins', function (Blueprint $table) {
            foreach ([
                'mobile_wallet_number', 'mobile_provider',
                'bank_routing_number', 'bank_account_number', 'bank_account_name',
                'bank_branch', 'bank_name',
                'payment_method',
            ] as $col) {
                if (Schema::hasColumn('admins', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
