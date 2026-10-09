<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foreign key names are unique in the whole database, so they carry the table prefix (`test_` for the test tables).
        $prefix = Schema::getConnection()->getTablePrefix();

        Schema::create('recruitment_application_answers', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('question_id');
            // Free text; erased on anonymisation. The other answer kinds stay.
            $table->text('answer_text')->nullable();
            $table->bigInteger('answer_number')->nullable();
            $table->boolean('answer_bool')->nullable();

            // PostgreSQL limits identifiers to 63 characters, so the foreign keys need explicit names.
            $table->foreign('application_id', $prefix.'recruitment_answers_application_foreign')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->foreign('question_id', $prefix.'recruitment_answers_question_foreign')->references('id')->on('recruitment_screening_questions')->restrictOnDelete();

            $table->unique(['application_id', 'question_id'], 'recruitment_answers_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_answers');
    }
};
