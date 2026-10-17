<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-interviewers.md` sections 3 and 4: the once-only markers of the 30-minute reminder to the panel and of the 72-hour scorecard follow-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dateTime('starting_reminded_at')->nullable();
            $table->dateTime('scorecard_escalated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropColumn(['starting_reminded_at', 'scorecard_escalated_at']);
        });
    }
};
