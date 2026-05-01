<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8.6 — Suppliers + Expense Categories + Bills.
 *
 * Five new tables:
 *
 *   suppliers
 *     Vendor master. Name, contact, BIN (BD VAT registration),
 *     payment terms (net_0/7/15/30), branch scope (null = HQ-wide),
 *     denormalised outstanding balance for fast lookup.
 *
 *   expense_categories
 *     Editable taxonomy: Rent, Utilities/Electricity, Utilities/Water,
 *     Utilities/Gas, Fuel, Maintenance, Cleaning, Licenses, Marketing,
 *     Office, Misc. Two-level (parent_id) so Utilities can sub into
 *     Electricity / Water / Gas.
 *
 *   expenses
 *     One bill / purchase. Header carries supplier, category, branch,
 *     bill_no (the supplier's invoice number), bill_date, due_date,
 *     subtotal/vat/total, status (draft/pending/partial/paid/cancelled),
 *     description, recorded_by, attachment.
 *
 *   expense_lines
 *     Line items for the bill — qty x unit_price for each material,
 *     consumable, fuel cylinder, etc. Optional category override per
 *     line (e.g. multi-category utility bill).
 *
 *   expense_payments
 *     Per-payment row. Amount, method (cash/bank/mobile/cheque),
 *     reference, paid_at, recorded_by_admin_id, cash_account_id (the
 *     account the money came out of). Auto-post writes one OUT row
 *     to that account per payment.
 *
 * Seeds: 18 BD-restaurant default expense categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 32)->nullable();
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('bin', 32)->nullable(); // BD VAT registration
            $table->enum('payment_terms', ['net_0', 'net_7', 'net_15', 'net_30', 'net_45', 'net_60'])
                ->default('net_0');
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->decimal('outstanding_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['name']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 40)->unique();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('color', 16)->default('#6A6A70');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_no', 40)->unique(); // EXP-2026-000001
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('bill_no', 80)->nullable(); // supplier's invoice ref
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0); // AIT etc
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0); // sum of expense_payments
            $table->enum('status', ['draft', 'pending', 'partial', 'paid', 'cancelled'])
                ->default('pending')->index();
            $table->text('description')->nullable();
            $table->string('attachment', 255)->nullable(); // optional invoice photo
            $table->unsignedBigInteger('recorded_by_admin_id')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id')->index();
            $table->unsignedBigInteger('category_id')->nullable()->index(); // line-level override
            $table->string('description', 255);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 40)->unique(); // PAY-2026-000001
            $table->unsignedBigInteger('expense_id')->index();
            $table->unsignedBigInteger('cash_account_id')->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->enum('method', ['cash', 'bank', 'mobile', 'cheque'])->default('cash');
            $table->string('reference', 80)->nullable(); // bank txn / cheque #
            $table->dateTime('paid_at');
            $table->unsignedBigInteger('paid_by_admin_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // -------- Seeds — BD-restaurant default expense categories --------
        $now = now();
        $cats = [
            // Top-level
            ['name' => 'Rent',         'code' => 'rent',         'color' => '#9B59B6', 'parent' => null, 'order' => 10, 'desc' => 'Monthly building / branch rent.'],
            ['name' => 'Utilities',    'code' => 'utilities',    'color' => '#4794FF', 'parent' => null, 'order' => 20, 'desc' => 'Electricity, water, gas, internet — separate sub-categories below.'],
            ['name' => 'Fuel',         'code' => 'fuel',         'color' => '#E84D4F', 'parent' => null, 'order' => 30, 'desc' => 'Generator diesel / petrol, vehicle fuel.'],
            ['name' => 'Raw Materials','code' => 'raw_materials','color' => '#1E8E3E', 'parent' => null, 'order' => 40, 'desc' => 'Meat, fish, vegetables, rice, oil — anything that goes into food.'],
            ['name' => 'Consumables',  'code' => 'consumables',  'color' => '#16A085', 'parent' => null, 'order' => 50, 'desc' => 'Napkins, packaging, takeaway boxes, dishwash liquid.'],
            ['name' => 'Maintenance',  'code' => 'maintenance',  'color' => '#E67E22', 'parent' => null, 'order' => 60, 'desc' => 'AC servicing, fridge repair, equipment fixes.'],
            ['name' => 'Cleaning',     'code' => 'cleaning',     'color' => '#34495E', 'parent' => null, 'order' => 70, 'desc' => 'Cleaning supplies, pest control, deep clean services.'],
            ['name' => 'Licenses',     'code' => 'licenses',     'color' => '#C0392B', 'parent' => null, 'order' => 80, 'desc' => 'Trade license, food license, BSTI, NBR, fire safety.'],
            ['name' => 'Marketing',    'code' => 'marketing',    'color' => '#E91E63', 'parent' => null, 'order' => 90, 'desc' => 'Foodpanda commission, ads, banner printing.'],
            ['name' => 'Office',       'code' => 'office',       'color' => '#607D8B', 'parent' => null, 'order' => 100, 'desc' => 'Stationery, printer ink, software subscriptions.'],
            ['name' => 'Transport',    'code' => 'transport',    'color' => '#FFC107', 'parent' => null, 'order' => 110, 'desc' => 'Vegetable runs, delivery rider top-up, local transport.'],
            ['name' => 'Misc',         'code' => 'misc',         'color' => '#6A6A70', 'parent' => null, 'order' => 200, 'desc' => 'Anything that doesn\'t fit elsewhere — keep light.'],
        ];
        $idByCode = [];
        foreach ($cats as $c) {
            $id = DB::table('expense_categories')->insertGetId([
                'name' => $c['name'], 'code' => $c['code'], 'parent_id' => $c['parent'],
                'color' => $c['color'], 'is_active' => true, 'sort_order' => $c['order'],
                'description' => $c['desc'],
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $idByCode[$c['code']] = $id;
        }
        // Sub-categories under Utilities
        $subs = [
            ['name' => 'Electricity', 'code' => 'utilities_electricity', 'parent_code' => 'utilities', 'order' => 21, 'desc' => 'PDB / DESCO / Palli Bidyut bills.'],
            ['name' => 'Water',       'code' => 'utilities_water',       'parent_code' => 'utilities', 'order' => 22, 'desc' => 'WASA / city water bills.'],
            ['name' => 'Gas',         'code' => 'utilities_gas',         'parent_code' => 'utilities', 'order' => 23, 'desc' => 'Titas pipeline gas + cylinder LPG (when not generator fuel).'],
            ['name' => 'Internet',    'code' => 'utilities_internet',    'parent_code' => 'utilities', 'order' => 24, 'desc' => 'ISP bills.'],
            ['name' => 'Phone',       'code' => 'utilities_phone',       'parent_code' => 'utilities', 'order' => 25, 'desc' => 'Branch landline / SMS gateway top-up.'],
        ];
        foreach ($subs as $s) {
            DB::table('expense_categories')->insert([
                'name' => $s['name'], 'code' => $s['code'],
                'parent_id' => $idByCode[$s['parent_code']],
                'color' => '#4794FF', 'is_active' => true,
                'sort_order' => $s['order'], 'description' => $s['desc'],
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
        Schema::dropIfExists('expense_lines');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('suppliers');
    }
};
