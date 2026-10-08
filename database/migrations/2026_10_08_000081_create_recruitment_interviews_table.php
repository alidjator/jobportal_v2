<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_interviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // `online` (a meeting link) or `onsite` (a place); the other column stays null.
            $table->string('format', 10);
            $table->string('location', 255)->nullable();
            $table->string('meeting_url', 500)->nullable();
            // Members that run and created the interview; no foreign keys to accounts.
            $table->unsignedBigInteger('interviewer_id');
            $table->unsignedBigInteger('created_by');
            // pending, confirmed, declined, cancelled or completed.
            $table->string('status', 10);
            $table->unsignedTinyInteger('proposals_used')->default(0);
            // A time the job seeker proposed and the team has not answered yet.
            $table->dateTime('proposed_starts_at')->nullable();
            // The application id while the interview is pending or confirmed and null afterwards, so an application has at most one active interview.
            $table->unsignedBigInteger('active_key')->nullable();
            $table->dateTime('reminded_at')->nullable();
            $table->timestamps();

            $table->foreign('application_id', Schema::getConnection()->getTablePrefix().'recruitment_interviews_application_foreign')->references('id')->on('recruitment_applications')->cascadeOnDelete();

            $table->unique('active_key', 'recruitment_interviews_active_unique');
            $table->index(['application_id', 'starts_at'], 'recruitment_interviews_application_time_index');
            $table->index(['status', 'starts_at'], 'recruitment_interviews_status_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_interviews');
    }
};
