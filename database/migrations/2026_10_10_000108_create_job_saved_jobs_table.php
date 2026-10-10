<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A job a job seeker saved to come back to: one row per person per job.
        Schema::create('job_saved_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('job_id')->constrained('job_jobs')->restrictOnDelete();
            $table->timestamp('saved_at');

            $table->unique(['user_id', 'job_id'], 'job_saved_jobs_user_job_unique');
            $table->index(['user_id', 'saved_at'], 'job_saved_jobs_user_saved_index');
            $table->index('job_id', 'job_saved_jobs_job_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_saved_jobs');
    }
};
