<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_screening_preset_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('preset_id')->constrained('recruitment_screening_presets')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('recruitment_screening_questions')->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_must_have')->default(false);
            $table->boolean('ideal_yes')->nullable();
            $table->unsignedBigInteger('ideal_min')->nullable();
            $table->unsignedBigInteger('ideal_max')->nullable();
            $table->timestamps();

            $table->unique(['preset_id', 'question_id'], 'recruitment_preset_questions_preset_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_screening_preset_questions');
    }
};
