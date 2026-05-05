<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.5 — per-item assignment + scheduled time.
 *
 *   assigned_designation_id : optional FK to `designations.id`. If set,
 *                             only staff with that designation see the
 *                             item on their phone. Null = anyone in branch.
 *   scheduled_time          : optional clock time (e.g. 07:00) the item
 *                             should be done at. Drives the 5-min-before
 *                             push reminder + UI sort order on the day.
 *
 * Strictly additive. Existing rows behave as "anyone, no specific time"
 * which matches Phase 3 behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_template_items', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_designation_id')->nullable()->after('template_id')->index();
            $table->time('scheduled_time')->nullable()->after('assigned_designation_id');
        });

        // Mirror onto the run snapshot so the audit row is self-contained
        // even after the template is edited or the designation is renamed.
        Schema::table('checklist_run_items', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_designation_id')->nullable()->after('template_item_id');
            $table->string('assigned_designation_name', 80)->nullable()->after('assigned_designation_id');
            $table->time('scheduled_time')->nullable()->after('assigned_designation_name');
            // Track whether we already fired the 5-min-before push so the
            // cron doesn't spam the same staff twice.
            $table->timestamp('reminder_sent_at')->nullable()->after('scheduled_time');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_template_items', function (Blueprint $table) {
            $table->dropColumn(['assigned_designation_id', 'scheduled_time']);
        });
        Schema::table('checklist_run_items', function (Blueprint $table) {
            $table->dropColumn([
                'assigned_designation_id',
                'assigned_designation_name',
                'scheduled_time',
                'reminder_sent_at',
            ]);
        });
    }
};
