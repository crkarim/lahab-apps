<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tip captured at checkout time. Independent of `extra_discount` /
 * `coupon_discount_amount` so reports can split tip-out from discount
 * accounting cleanly. Stays null for orders that came in via the
 * customer app or admin POS where tipping isn't a UI concept yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tip_amount', 10, 2)->nullable()->after('extra_discount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tip_amount');
        });
    }
};
