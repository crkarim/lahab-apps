<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8.1 + 8.2 — Accounts ledger foundation.
 *
 * `cash_accounts` — every "wallet" the company holds money in:
 *   - Bank (DBBL Current 8721, BRAC SND 1234, ...)
 *   - MFS (bKash Owner Personal, Nagad Branch 2, ...)
 *   - Cash (HQ Safe, Branch tills)
 *   - Cheque clearing (optional staging account for cheques received)
 *
 * `account_transactions` — every Taka movement. amount = gross principal
 * (pre-charge); charge tracked separately so disbursement reports can
 * sum bKash/bank fees in isolation. vat_input + vat_output also separate
 * so the VAT register can reconcile to NBR submissions.
 *
 * Net balance impact:
 *   in  → +amount − charge
 *   out → −amount − charge
 *
 * Transfers create two rows in a DB transaction (one OUT, one IN), linked
 * via paired_txn_id. The charge belongs to one leg only (usually OUT).
 *
 * `current_balance` on cash_accounts is denormalised for fast reads;
 * controllers recompute it from transactions on every write. A
 * `recomputeBalance()` model method is the canonical resync if anything
 * drifts.
 *
 * `category_id` + `supplier_id` columns omitted for now — the categories
 * and suppliers tables come in Phase 8.4. Description string carries
 * categorization for manual entries until then.
 *
 * Seeds: one HQ Cash on Hand account + one Cash Till per existing branch.
 * Everything else (bKash accounts, banks) the user creates themselves —
 * each operation has its own setup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 32);
            $table->enum('type', ['cash', 'bank', 'mfs', 'cheque']);
            // For mfs: bkash/nagad/rocket/upay; for bank: bank name.
            // Free text since BD has 60+ banks.
            $table->string('provider', 60)->nullable();
            $table->string('account_number', 40)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            // Opening balance is the company's starting position when this
            // account is added to the system. Closing balance = opening +
            // sum of all txn impacts since.
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_date')->nullable();
            $table->decimal('current_balance', 14, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('color', 16)->default('#6A6A70');
            $table->text('notes')->nullable();
            $table->timestamps();
            // Code unique within branch scope (branch tills can share "till" code across branches).
            $table->unique(['code', 'branch_id']);
        });

        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            // Auto-generated human-readable id: TXN-2026-000001.
            $table->string('txn_no', 32)->unique();
            $table->unsignedBigInteger('account_id')->index();
            $table->enum('direction', ['in', 'out']);

            $table->decimal('amount', 14, 2);                 // gross principal
            $table->decimal('charge', 12, 2)->default(0);     // bKash/bank fee
            $table->decimal('vat_input', 12, 2)->default(0);  // VAT we paid (already inside amount on OUT)
            $table->decimal('vat_output', 12, 2)->default(0); // VAT we collected (already inside amount on IN)
            $table->decimal('tax_amount', 12, 2)->default(0); // AIT / withholding

            // Polymorphic reference — Phase 8.4-8.6 wires POS/HRM/bills here.
            $table->string('ref_type', 50)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();

            // Transfer linkage — when set, this row is one leg of a transfer
            // and paired_txn_id points at the OTHER leg.
            $table->unsignedBigInteger('paired_txn_id')->nullable()->index();

            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->text('description');
            $table->dateTime('transacted_at')->index();
            $table->unsignedBigInteger('recorded_by_admin_id')->nullable();
            $table->timestamps();
            $table->index(['ref_type', 'ref_id']);
            $table->index(['account_id', 'transacted_at']);
        });

        // Seed: one HQ Cash on Hand + one Cash Till per existing branch.
        // These cover the common "where does daily till money sit" base case
        // out of the box. Banks + bKash accounts the user adds themselves.
        $now = now();
        $hqId = DB::table('cash_accounts')->insertGetId([
            'name'            => 'HQ Cash on Hand',
            'code'            => 'hq_cash',
            'type'            => 'cash',
            'provider'        => null,
            'account_number'  => null,
            'branch_id'       => null,
            'opening_balance' => 0,
            'opening_date'    => $now->toDateString(),
            'current_balance' => 0,
            'is_active'       => true,
            'sort_order'      => 10,
            'color'           => '#1E8E3E',
            'notes'           => 'Default HQ-level cash safe. Edit or rename as needed.',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        // One till per existing branch.
        $branches = DB::table('branches')->select('id', 'name')->get();
        $sort = 100;
        foreach ($branches as $b) {
            DB::table('cash_accounts')->insert([
                'name'            => $b->name . ' — Cash Till',
                'code'            => 'till',
                'type'            => 'cash',
                'provider'        => null,
                'account_number'  => null,
                'branch_id'       => $b->id,
                'opening_balance' => 0,
                'opening_date'    => $now->toDateString(),
                'current_balance' => 0,
                'is_active'       => true,
                'sort_order'      => $sort,
                'color'           => '#4794FF',
                'notes'           => 'Daily till for branch ' . $b->name . '. Top up + close out daily.',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            $sort += 10;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('cash_accounts');
    }
};
