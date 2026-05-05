<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends `attendance_logs` so the My Lahab staff app can record:
 *   - selfie_path : storage/app/public/attendance/<file> taken at clock-in
 *   - method enum : add `mobile_qr` so we can distinguish app-driven scans
 *                   from the existing manual / shift_open / shift_close /
 *                   biometric paths used by the admin panel.
 *
 * Geo-coords (lat/lng captured at scan) live alongside as separate
 * decimal columns so we can later show them on a map for audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('selfie_path', 255)->nullable()->after('notes');
            $table->decimal('clock_in_lat', 10, 7)->nullable()->after('selfie_path');
            $table->decimal('clock_in_lng', 10, 7)->nullable()->after('clock_in_lat');
            $table->decimal('clock_out_lat', 10, 7)->nullable()->after('clock_in_lng');
            $table->decimal('clock_out_lng', 10, 7)->nullable()->after('clock_out_lat');
        });

        // ENUM extension: append the new value, keep all existing ones.
        // Doing it as raw SQL because Laravel's schema builder can't extend
        // ENUMs without a doctrine/dbal dependency we don't have.
        DB::statement(
            "ALTER TABLE attendance_logs MODIFY method ENUM('manual','shift_open','shift_close','biometric','mobile_qr') NOT NULL DEFAULT 'manual'"
        );
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'selfie_path',
                'clock_in_lat', 'clock_in_lng',
                'clock_out_lat', 'clock_out_lng',
            ]);
        });

        // Best-effort rollback. Will fail if any row already has method='mobile_qr';
        // operator must DELETE / UPDATE those rows first.
        DB::statement(
            "ALTER TABLE attendance_logs MODIFY method ENUM('manual','shift_open','shift_close','biometric') NOT NULL DEFAULT 'manual'"
        );
    }
};
