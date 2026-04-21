<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'receipt_token')) {
                $table->string('receipt_token', 40)->nullable()->unique()->after('transaction_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'receipt_token')) {
                $table->dropUnique(['receipt_token']);
                $table->dropColumn('receipt_token');
            }
        });
    }
};
