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
            $table->string('label', 100);
            $table->unsignedTinyInteger('position');
        });

        // A choice is unique per question ignoring case.
        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'recruitment_screening_question_options';

        $connection->statement(
            "CREATE UNIQUE INDEX \"recruitment_screening_options_question_label_unique\" ON \"{$tableName}\" (\"question_id\", lower(\"label\"))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_screening_question_options');
    }
};
