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

        Schema::create('recruitment_application_answer_options', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->unsignedBigInteger('answer_id');
            $table->unsignedBigInteger('option_id');

            // PostgreSQL limits identifiers to 63 characters, so the foreign keys need explicit names.
            $table->foreign('answer_id', $prefix.'recruitment_answer_options_answer_foreign')->references('id')->on('recruitment_application_answers')->cascadeOnDelete();
            $table->foreign('option_id', $prefix.'recruitment_answer_options_option_foreign')->references('id')->on('recruitment_screening_question_options')->restrictOnDelete();

            $table->unique(['answer_id', 'option_id'], 'recruitment_answer_options_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_answer_options');
    }
};
