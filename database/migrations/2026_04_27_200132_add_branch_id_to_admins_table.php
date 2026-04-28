<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branch awareness on the admin (employee) record. Semantics:
 *   - NULL  → HQ admin / owner (sees and acts across every branch)
 *   - <id>  → branch staff (waiter, manager) scoped to that branch
 *
 * Used by the waiter API to scope queries (tables, products, orders) to
 * the staff member's branch. Keep nullable so the existing master admin
 * row keeps its current cross-branch behaviour without backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (!Schema::hasColumn('admins', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('admin_role_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'branch_id')) {
                $table->dropIndex(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
};
