<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_job_questions', function (Blueprint $table): void {
            $table->id();
            // The job lives in another context: a plain indexed column, no foreign key.
            $table->unsignedBigInteger('job_id')->index();
            $table->foreignId('question_id')->constrained('recruitment_screening_questions')->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_must_have')->default(false);
            $table->boolean('ideal_yes')->nullable();
            $table->unsignedBigInteger('ideal_min')->nullable();
            $table->unsignedBigInteger('ideal_max')->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'question_id'], 'recruitment_job_questions_job_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_job_questions');
    }
};
