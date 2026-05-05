<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * `branches.attendance_qr_token` is the random opaque payload encoded into
 * the printed QR code each branch posts at the time-clock spot. Staff scan
 * it via the My Lahab app to prove they're physically at the branch
 * (combined with selfie + GPS-radius check).
 *
 * Static printed QR is the chosen design (laminated near the entrance).
 * Rotation-capable infra (server-side rolling token) can be layered on
 * later without breaking the staff app — the column stays the same.
 *
 * Tokens are generated for ALL existing branches in this migration so the
 * admin panel can immediately render a QR for each one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('attendance_qr_token', 64)->nullable()->unique()->after('coverage');
            $table->unsignedSmallInteger('attendance_geo_radius_m')->default(150)->after('attendance_qr_token');
        });

        $branches = DB::table('branches')->select('id')->get();
        foreach ($branches as $branch) {
            DB::table('branches')
                ->where('id', $branch->id)
                ->update(['attendance_qr_token' => 'lahab-att-' . Str::random(40)]);
        }
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['attendance_qr_token']);
            $table->dropColumn(['attendance_qr_token', 'attendance_geo_radius_m']);
        });
    }
};
