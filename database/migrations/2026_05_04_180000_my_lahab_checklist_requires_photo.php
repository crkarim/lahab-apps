<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `requires_photo` per checklist step.
 *
 * When true (the default for new items), staff cannot mark the step
 * complete with a tap — they must attach a camera photo as proof.
 * When false, a single tap toggles the step.
 *
 * Mirrored onto run_items as a snapshot so retroactive template edits
 * never alter past audits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_template_items', function (Blueprint $table) {
            $table->boolean('requires_photo')->default(true)->after('is_required');
        });
        Schema::table('checklist_run_items', function (Blueprint $table) {
            $table->boolean('requires_photo')->default(true)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_template_items', function (Blueprint $table) {
            $table->dropColumn('requires_photo');
        });
        Schema::table('checklist_run_items', function (Blueprint $table) {
            $table->dropColumn('requires_photo');
        });
    }
};
