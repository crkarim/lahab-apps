<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'kot_number')) {
                $table->string('kot_number', 20)->nullable()->index()->after('receipt_token');
            }
            if (!Schema::hasColumn('orders', 'kot_sent_at')) {
                $table->timestamp('kot_sent_at')->nullable()->after('kot_number');
            }
            if (!Schema::hasColumn('orders', 'kot_print_count')) {
                $table->unsignedSmallInteger('kot_print_count')->default(0)->after('kot_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['kot_number', 'kot_sent_at', 'kot_print_count'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
