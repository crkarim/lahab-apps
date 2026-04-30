<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds a "Chef" admin role with kitchen_management permission only.
 *
 * Lets the kitchen monitor log in to /admin/kitchen/scan without
 * granting access to orders, payments, settings, etc. The login
 * redirect (Admin\Auth\LoginController) sends Chef-role users
 * straight to the scan page after sign-in.
 *
 * Idempotent: safe to re-run; checks for an existing 'Chef' row by
 * name before inserting.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('admin_roles')->where('name', 'Chef')->exists();
        if (!$exists) {
            DB::table('admin_roles')->insert([
                'name'          => 'Chef',
                'module_access' => json_encode(['kitchen_management']),
                'status'        => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('admin_roles')->where('name', 'Chef')->delete();
    }
};
