<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the columns the My Lahab staff app needs to authenticate against the
 * existing `admins` table. Strictly additive:
 *   - `app_pin_hash`         nullable bcrypt of the staff's 4-6 digit PIN.
 *                            NULL means PIN not set yet (admin must reset).
 *   - `app_login_enabled`    boolean opt-in. Default 0 so existing admins
 *                            don't silently gain mobile-app access — the
 *                            office manager toggles it from /admin/employee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('app_pin_hash', 100)->nullable()->after('password');
            $table->boolean('app_login_enabled')->default(false)->after('app_pin_hash');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['app_pin_hash', 'app_login_enabled']);
        });
    }
};
