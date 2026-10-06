<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_screening_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained('recruitment_screening_questions')->cascadeOnDelete();
            // The case-insensitive collation makes the unique key a case-insensitive "unique choice per question" rule.
            $table->string('label', 100)->collation('utf8mb4_unicode_ci');
            $table->unsignedTinyInteger('position');

            $table->unique(['question_id', 'label'], 'recruitment_screening_options_question_label_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_screening_question_options');
    }
};
