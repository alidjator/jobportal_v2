<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-interviewers.md` section 2: the answer of the main interviewer (on the interview) and of each extra panel member (on the panel row) to the
 * invitation: `pending` (default), `accepted` or `declined`, with an optional note and the time of the answer. A new interview row starts `pending`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->string('interviewer_rsvp', 10)->default('pending');
            $table->string('interviewer_rsvp_note', 500)->nullable();
            $table->dateTime('interviewer_rsvp_at')->nullable();
        });

        Schema::table('recruitment_interview_participants', function (Blueprint $table): void {
            $table->string('rsvp', 10)->default('pending');
            $table->string('rsvp_note', 500)->nullable();
            $table->dateTime('rsvp_at')->nullable();
        });

        DB::statement("ALTER TABLE recruitment_interviews ADD CONSTRAINT recruitment_interviews_interviewer_rsvp_check CHECK (interviewer_rsvp IN ('pending', 'accepted', 'declined'))");
        DB::statement("ALTER TABLE recruitment_interview_participants ADD CONSTRAINT recruitment_interview_participants_rsvp_check CHECK (rsvp IN ('pending', 'accepted', 'declined'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE recruitment_interviews DROP CONSTRAINT recruitment_interviews_interviewer_rsvp_check');
        DB::statement('ALTER TABLE recruitment_interview_participants DROP CONSTRAINT recruitment_interview_participants_rsvp_check');

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropColumn(['interviewer_rsvp', 'interviewer_rsvp_note', 'interviewer_rsvp_at']);
        });

        Schema::table('recruitment_interview_participants', function (Blueprint $table): void {
            $table->dropColumn(['rsvp', 'rsvp_note', 'rsvp_at']);
        });
    }
};
