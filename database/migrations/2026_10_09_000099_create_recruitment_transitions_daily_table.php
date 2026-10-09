<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stage moves per Jakarta day, with the seconds spent in the stage that was left and since the application was submitted. Counts and sums only, so any range can be
        // added up and an average is `sum / count`; a median cannot be added up and is read live from the stage history.
        Schema::create('recruitment_transitions_daily', function (Blueprint $table): void {
            $table->date('day');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('job_id');
            // The recruiter that made the move; 0 for the applicant or the system.
            $table->unsignedBigInteger('actor_id');
            $table->string('from_type', 20);
            $table->string('to_type', 20);
            // `none` when the move is not a rejection or has no reason.
            $table->string('rejection_reason', 40);
            $table->string('source', 15);
            $table->string('match_band', 10);
            $table->unsignedInteger('transitions');
            $table->unsignedBigInteger('seconds_in_stage');
            $table->unsignedBigInteger('seconds_since_applied');

            $table->primary(['day', 'job_id', 'actor_id', 'from_type', 'to_type', 'rejection_reason', 'source', 'match_band'], 'recruitment_transitions_daily_primary');
            $table->index(['company_id', 'day'], 'recruitment_transitions_daily_company_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_transitions_daily');
    }
};
