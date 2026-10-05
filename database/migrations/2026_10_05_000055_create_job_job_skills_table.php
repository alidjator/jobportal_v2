<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_job_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_id')->constrained('job_jobs')->cascadeOnDelete();
            $table->unsignedBigInteger('skill_id')->index();
            $table->timestamp('created_at')->nullable();

            $table->unique(['job_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_job_skills');
    }
};
