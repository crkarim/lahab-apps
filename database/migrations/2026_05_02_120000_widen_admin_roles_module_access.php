<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug fix — `admin_roles.module_access` was `VARCHAR(255)` and silently
 * truncated when more than ~10-12 module keys were ticked. Truncation
 * produced invalid JSON, which decoded to NULL inside
 * `Helpers::module_permission_check()`, so every permission lookup
 * returned false even when the role was supposedly granted.
 *
 * Symptoms:
 *   - Save a role with most modules checked → admin under that role
 *     gets "Access Denied" on every URL
 *   - phpMyAdmin shows a `module_access` value that ends mid-key
 *     (e.g. `["...,"hrm_employee` with no closing `"]`)
 *
 * Fix: widen to TEXT (65k bytes — plenty for any realistic combination
 * of module keys).
 *
 * Roles saved before this migration may have truncated values; the
 * fix is to re-save those roles via /admin/custom-role/edit/{id} once
 * the column is widened. The migration intentionally does NOT try to
 * "repair" them — we don't know what the operator intended to grant.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE admin_roles MODIFY module_access TEXT NULL');
    }

    public function down(): void
    {
        // Best-effort rollback. If any row exceeds 255 chars, the ALTER
        // will fail — that's correct: we don't want to silently
        // re-introduce the original truncation bug. Operator can run
        // a DELETE first if they really want to roll back.
        DB::statement('ALTER TABLE admin_roles MODIFY module_access VARCHAR(255) NULL');
    }
};
