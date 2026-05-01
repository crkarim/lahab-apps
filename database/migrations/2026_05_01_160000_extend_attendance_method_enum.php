<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extends attendance_logs.method enum to accept 'biometric' — used by
 * the ZKTeco / fingerprint device CSV import. Existing rows keep their
 * value (manual / shift_open / shift_close) untouched.
 *
 * MySQL ENUM alter is a raw statement because Laravel's DBAL doesn't
 * support enum modifications portably across drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance_logs MODIFY COLUMN method ENUM('manual', 'shift_open', 'shift_close', 'biometric') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        // Best-effort rollback — would fail if any rows already use 'biometric'.
        DB::statement("ALTER TABLE attendance_logs MODIFY COLUMN method ENUM('manual', 'shift_open', 'shift_close') NOT NULL DEFAULT 'manual'");
    }
};
