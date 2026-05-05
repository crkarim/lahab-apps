<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff-facing notice board: announcements pushed by the office to all or
 * one branch. Read receipts power the unread badge on the My Lahab home tab.
 *
 *   staff_notices       — the post itself + scheduling
 *   staff_notice_reads  — per-staff read receipt
 *
 * Both tables are net-new — zero risk to any existing flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notices', function (Blueprint $table) {
            $table->id();
            // Null branch_id = global (every branch).
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('title', 200);
            $table->text('body');
            $table->string('image', 255)->nullable();
            // Future-proofing: allow scheduling, but default to "now".
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            // Soft-pin to the top of the inbox.
            $table->boolean('is_pinned')->default(false);
            // For audit / "posted by ___ at ___" rendering.
            $table->unsignedBigInteger('posted_by_admin_id')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_notice_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_notice_id');
            $table->unsignedBigInteger('admin_id');
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['staff_notice_id', 'admin_id'], 'staff_notice_reads_unique');
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notice_reads');
        Schema::dropIfExists('staff_notices');
    }
};
