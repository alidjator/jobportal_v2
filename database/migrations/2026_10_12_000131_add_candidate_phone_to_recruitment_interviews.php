<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voice interviews (`docs/design/phase-7-tele-interview.md`, form 3, part 1): the number the job seeker confirmed for this interview, in E.164. It is the number
 * a voice interview is dialled on, or the optional fallback number of a video interview. The `voice` format needs no column of its own (the format is a plain string).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->string('candidate_phone', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropColumn('candidate_phone');
        });
    }
};
