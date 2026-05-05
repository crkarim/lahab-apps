<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-person checklist submission.
 *
 * One row per (run, admin) pair recording when that person finished
 * their assigned items. The run as a whole is auto-completed when
 * every distinct assignee has submitted.
 *
 * Why a separate table (vs. a JSON column on checklist_runs):
 *   - Cheap "who's still pending" queries for the admin run-detail page
 *   - Per-row timestamp for audit
 *   - Survives admin renames (admin_name snapshot)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_run_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('admin_id')->index();
            $table->string('admin_name', 120)->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->text('note')->nullable();

            // One submission per person per run.
            $table->unique(['run_id', 'admin_id'], 'checklist_run_submissions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_run_submissions');
    }
};
