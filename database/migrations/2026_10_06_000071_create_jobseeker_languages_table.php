<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_languages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('language_id')->constrained('reference_languages')->restrictOnDelete();
            $table->string('proficiency', 20);

            $table->unique(['user_id', 'language_id'], 'jobseeker_languages_user_language_unique');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'jobseeker_languages';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_proficiency_check` CHECK (`proficiency` IN ('basic', 'conversational', 'fluent', 'native'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_languages');
    }
};
