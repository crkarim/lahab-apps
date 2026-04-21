<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'placed_by_admin_id')) {
                $table->unsignedBigInteger('placed_by_admin_id')->nullable()->index()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'placed_by_admin_id')) {
                $table->dropColumn('placed_by_admin_id');
            }
        });
    }
};
