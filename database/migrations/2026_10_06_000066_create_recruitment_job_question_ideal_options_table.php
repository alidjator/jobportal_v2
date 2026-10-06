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

        Schema::create('recruitment_job_question_ideal_options', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->unsignedBigInteger('job_question_id');
            $table->unsignedBigInteger('option_id');

            // MariaDB limits identifiers to 64 characters, so the foreign keys need explicit names (the generated ones are too long with the `test_` prefix).
            $table->foreign('job_question_id', $prefix.'recruitment_ideal_options_job_question_foreign')->references('id')->on('recruitment_job_questions')->cascadeOnDelete();
            $table->foreign('option_id', $prefix.'recruitment_ideal_options_option_foreign')->references('id')->on('recruitment_screening_question_options')->restrictOnDelete();

            $table->unique(['job_question_id', 'option_id'], 'recruitment_ideal_options_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_job_question_ideal_options');
    }
};
