<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video interview rooms (`docs/design/phase-7-tele-interview.md`, form 1, part 2): the Janus room of an interview (created at the first join, closed after the
 * join window), the lobby state of the candidate, and the members who were given a join token so the tokens can be removed when the room closes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            // The Janus room number; null until the first join. The pin and the secret are encrypted by the application.
            $table->unsignedBigInteger('video_room_id')->nullable();
            $table->text('video_room_pin')->nullable();
            $table->text('video_room_secret')->nullable();
            $table->dateTime('video_room_closed_at')->nullable();
            // Last time the candidate asked to enter (join or lobby poll); the candidate counts as waiting for a minute after it.
            $table->dateTime('candidate_waiting_at')->nullable();
            $table->dateTime('admitted_at')->nullable();
            $table->unsignedBigInteger('admitted_by')->nullable();

            $table->foreign('admitted_by', 'recruitment_interviews_admitted_by_foreign')->references('id')->on('identity_users')->restrictOnDelete();
            $table->index(['video_room_id', 'video_room_closed_at'], 'recruitment_interviews_video_room_index');
        });

        Schema::create('recruitment_interview_video_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')->constrained('recruitment_interviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();
            $table->dateTime('created_at');

            $table->unique(['interview_id', 'user_id'], 'recruitment_interview_video_tokens_pair_unique');
            $table->index('user_id', 'recruitment_interview_video_tokens_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_interview_video_tokens');

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropForeign('recruitment_interviews_admitted_by_foreign');
            $table->dropIndex('recruitment_interviews_video_room_index');
            $table->dropColumn(['video_room_id', 'video_room_pin', 'video_room_secret', 'video_room_closed_at', 'candidate_waiting_at', 'admitted_at', 'admitted_by']);
        });
    }
};
