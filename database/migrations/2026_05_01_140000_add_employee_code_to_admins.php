<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `employee_code` to admins — the short identifier we'll match
 * against the ZKTeco biometric device's "User ID" / "Enroll Number"
 * when the attendance sync ships.
 *
 * Why VARCHAR(20) and not int: some setups use codes like "E001" or
 * "WAITER-12"; the device just stores whatever the operator typed.
 * Indexed (unique) so the sync can do `where employee_code = ?` per
 * record without a table scan.
 *
 * Nullable so existing admins / current operations don't need a code
 * before the device is wired in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('employee_code', 20)->nullable()->after('id');
            $table->unique('employee_code', 'admins_employee_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropUnique('admins_employee_code_unique');
            $table->dropColumn('employee_code');
        });
    }
};
