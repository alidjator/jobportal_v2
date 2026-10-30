<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-rejections.md` section 2: a rejection that waits an undo window before it takes effect. One row per application (the application stays in its stage while
 * it waits); `commit_at` is when `recruitment:commit-rejections` applies it, and `batch_id` ties the rows of one bulk action so the whole action can be undone at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_pending_rejections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('recruitment_applications')->cascadeOnDelete();
            // The stage the application was in when the rejection was asked: if it has left it by the time the rejection is due, the rejection is dropped.
            $table->foreignId('stage_id')->constrained('recruitment_stages')->restrictOnDelete();
            $table->string('reason', 40);
            $table->text('note')->nullable();
            // The account is a plain column: accounts live in another context and have no foreign key.
            $table->unsignedBigInteger('requested_by_user_id');
            $table->timestamp('requested_at');
            $table->timestamp('commit_at')->index();
            $table->uuid('batch_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_pending_rejections');
    }
};
