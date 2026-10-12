<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voice interviews are recorded too (`docs/design/phase-7-tele-interview.md`, form 3, part 3). A recording is `video` (a video room, converted from Janus
 * tracks) or `audio` (the voice bridge, fetched from the voice server); every recording that exists today is video.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_interview_recordings', function (Blueprint $table): void {
            $table->string('media', 5)->default('video')->after('interview_id');
        });

        DB::statement("ALTER TABLE recruitment_interview_recordings ADD CONSTRAINT recruitment_interview_recordings_media_check CHECK (media IN ('video', 'audio'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE recruitment_interview_recordings DROP CONSTRAINT recruitment_interview_recordings_media_check');

        Schema::table('recruitment_interview_recordings', function (Blueprint $table): void {
            $table->dropColumn('media');
        });
    }
};
