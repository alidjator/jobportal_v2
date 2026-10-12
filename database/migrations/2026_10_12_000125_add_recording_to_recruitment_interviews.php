<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video interviews (`docs/design/phase-7-tele-interview.md`, form 1, part 1): whether the team marked the interview as recorded and the job seeker's answer
 * to the recording. The `video` format needs no column of its own (the format is a plain string).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->boolean('recorded')->default(false);
            // granted, refused or withdrawn; null until the job seeker answers (and always null for an interview that is not recorded).
            $table->string('recording_consent', 10)->nullable();
            $table->dateTime('recording_consent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropColumn(['recorded', 'recording_consent', 'recording_consent_at']);
        });
    }
};
