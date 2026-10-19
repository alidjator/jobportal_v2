<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-interview-reschedule.md`: the reason and the time of the job seeker's proposal, the team's note of a no-show, the claim of the job seeker's
 * reminder two hours before, and a CHECK on the status (it had none) that includes `no_show`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->string('proposal_reason', 500)->nullable();
            $table->timestamp('proposal_made_at')->nullable();
            $table->string('no_show_note', 1000)->nullable();
            $table->timestamp('candidate_soon_reminded_at')->nullable();
        });

        DB::statement("ALTER TABLE recruitment_interviews ADD CONSTRAINT recruitment_interviews_status_check CHECK (status IN ('pending', 'confirmed', 'declined', 'cancelled', 'completed', 'no_show'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE recruitment_interviews DROP CONSTRAINT recruitment_interviews_status_check');

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropColumn(['proposal_reason', 'proposal_made_at', 'no_show_note', 'candidate_soon_reminded_at']);
        });
    }
};
