<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_skills', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('skill_id')->constrained('reference_skills')->restrictOnDelete();
            $table->unsignedTinyInteger('position');

            $table->unique(['user_id', 'skill_id'], 'jobseeker_skills_user_skill_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_skills');
    }
};
