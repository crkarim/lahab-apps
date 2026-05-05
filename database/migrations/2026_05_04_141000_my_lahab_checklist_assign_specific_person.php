<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.6 — per-person assignment.
 *
 * Adds `assigned_admin_id` to checklist_template_items + the run-item
 * snapshot. Resolution order at the staff app:
 *   - assigned_admin_id set → only that specific staff sees it
 *   - else assigned_designation_id set → anyone with that designation sees it
 *   - else → anyone in the branch sees it
 *
 * Three cleaners with different responsibilities can now each get their
 * own personalised steps in the same checklist template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_template_items', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_admin_id')->nullable()->after('assigned_designation_id')->index();
        });
        Schema::table('checklist_run_items', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_admin_id')->nullable()->after('assigned_designation_name')->index();
            $table->string('assigned_admin_name', 120)->nullable()->after('assigned_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_template_items', function (Blueprint $table) {
            $table->dropColumn('assigned_admin_id');
        });
        Schema::table('checklist_run_items', function (Blueprint $table) {
            $table->dropColumn(['assigned_admin_id', 'assigned_admin_name']);
        });
    }
};
