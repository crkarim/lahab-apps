<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — open/close checklists + daily reminders for the My Lahab
 * staff app.
 *
 *   checklist_templates       — reusable named checklist (e.g. "Morning
 *                                Open", "Closing", "Weekly Deep Clean").
 *                                Scoped to a branch or global.
 *   checklist_template_items  — line items (steps) for a template.
 *   checklist_runs            — one execution of a template on a given
 *                                day, by a specific staff.
 *   checklist_run_items       — per-step state for a run, with optional
 *                                photo + note for audit.
 *
 * All four tables are net-new — zero impact on existing flows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            // Null branch_id = global (every branch sees it).
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name', 120);
            // What time of day this checklist is for. The /today endpoint
            // uses this to bucket items into Open / Daily / Close groups.
            $table->enum('kind', ['open', 'daily', 'close', 'weekly'])->default('daily')->index();
            // Sort within its kind on the home screen.
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_template_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id')->index();
            $table->string('label', 200);
            $table->unsignedInteger('sort_order')->default(0);
            // Required items must be checked before the run can be marked
            // complete. Optional items can be skipped.
            $table->boolean('is_required')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('started_by_admin_id');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            // The "logical" date the run is for (separates Today from
            // Yesterday across midnight boundaries during late closes).
            $table->date('run_date')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'run_date'], 'checklist_runs_template_date');
        });

        Schema::create('checklist_run_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('template_item_id');
            // Snapshot the label + required flag at run-creation time so
            // template edits don't retroactively change the audit trail.
            $table->string('label_snapshot', 200);
            $table->boolean('is_required')->default(true);
            $table->timestamp('checked_at')->nullable();
            $table->unsignedBigInteger('checked_by_admin_id')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'template_item_id'], 'checklist_run_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_run_items');
        Schema::dropIfExists('checklist_runs');
        Schema::dropIfExists('checklist_template_items');
        Schema::dropIfExists('checklist_templates');
    }
};
