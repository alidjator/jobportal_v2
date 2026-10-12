<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voice interviews (`docs/design/phase-7-tele-interview.md`, form 3, part 2): when the voice bridge of an interview was opened and closed, the temporary
 * browser endpoints handed to the team, the call log, and the voice server channel of an attendance. The bridge itself is named after the interview
 * (`interview-{id}`), so it needs no column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dateTime('voice_opened_at')->nullable();
            $table->dateTime('voice_closed_at')->nullable();
            $table->index(['voice_opened_at', 'voice_closed_at'], 'recruitment_interviews_voice_index');
        });

        Schema::create('recruitment_interview_voice_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')->constrained('recruitment_interviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();
            // The browser account on the voice server (`t{interview}-{user}`); removed when the voice session closes.
            $table->string('username', 40);
            $table->dateTime('created_at');

            $table->unique(['interview_id', 'user_id'], 'recruitment_interview_voice_endpoints_pair_unique');
            $table->index('user_id', 'recruitment_interview_voice_endpoints_user_index');
        });

        // One row per time the team dialled the candidate. `number` is the E.164 number dialled; the rows go with the interview.
        Schema::create('recruitment_interview_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')->constrained('recruitment_interviews')->cascadeOnDelete();
            $table->foreignId('started_by')->constrained('identity_users')->restrictOnDelete();
            // dialing, ringing, answered, no_answer, busy, failed or ended (an answered call that finished).
            $table->string('status', 10);
            $table->string('number', 16);
            // The voice server channel (`call-{id}`), which its events are matched to.
            $table->string('channel_id', 64)->unique('recruitment_interview_calls_channel_unique');
            $table->dateTime('dialed_at');
            $table->dateTime('answered_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('hangup_cause')->nullable();
            $table->timestamps();

            $table->index(['interview_id', 'status'], 'recruitment_interview_calls_interview_index');
            $table->index(['status', 'dialed_at'], 'recruitment_interview_calls_status_index');
            $table->index('answered_at', 'recruitment_interview_calls_answered_index');
        });

        Schema::table('recruitment_interview_attendances', function (Blueprint $table): void {
            // The voice server channel of a voice join, so the later hangup finds this row.
            $table->string('channel_id', 64)->nullable();
            $table->index('channel_id', 'recruitment_interview_attendances_channel_index');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_interview_attendances', function (Blueprint $table): void {
            $table->dropIndex('recruitment_interview_attendances_channel_index');
            $table->dropColumn('channel_id');
        });

        Schema::dropIfExists('recruitment_interview_calls');
        Schema::dropIfExists('recruitment_interview_voice_endpoints');

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropIndex('recruitment_interviews_voice_index');
            $table->dropColumn(['voice_opened_at', 'voice_closed_at']);
        });
    }
};
