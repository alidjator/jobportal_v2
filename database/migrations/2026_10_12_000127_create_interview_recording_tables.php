<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video interview attendance and recordings (`docs/design/phase-7-tele-interview.md`, form 1, part 3). Attendance is a log written from the video server's
 * events, so `user_id` has no foreign key. A recording row exists from the moment a team member starts recording until it is deleted with its file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_interview_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')->constrained('recruitment_interviews')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            // `company` or `candidate`.
            $table->string('role', 10);
            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable();
            // The video server session of the connection, so the later `leaving` event finds this row.
            $table->unsignedBigInteger('janus_session_id')->nullable();

            $table->index(['interview_id', 'joined_at'], 'recruitment_interview_attendances_interview_index');
            $table->index('janus_session_id', 'recruitment_interview_attendances_session_index');
        });

        Schema::create('recruitment_interview_recordings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')->constrained('recruitment_interviews')->cascadeOnDelete();
            // recording, processing (converting and storing), ready or failed.
            $table->string('status', 12);
            $table->foreignId('started_by')->constrained('identity_users')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('stopped_at')->nullable();
            // The converted file on the volume shared with the video container, until it is moved to the private disk.
            $table->string('source_file', 255)->nullable();
            // A random name on the private disk.
            $table->string('path', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['interview_id', 'status'], 'recruitment_interview_recordings_interview_index');
            $table->index(['status', 'expires_at'], 'recruitment_interview_recordings_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_interview_recordings');
        Schema::dropIfExists('recruitment_interview_attendances');
    }
};
