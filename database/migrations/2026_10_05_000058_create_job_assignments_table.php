<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_id')->constrained('job_jobs')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['job_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_assignments');
    }
};
