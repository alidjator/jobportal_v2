<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_screening_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('text', 300);
            $table->timestamp('archived_at')->nullable();
            // The account is a plain column: accounts live in another context and have no foreign key.
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            $table->index(['company_id', 'archived_at'], 'recruitment_screening_questions_company_archived_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'recruitment_screening_questions';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_type_check` CHECK (`type` IN ('text', 'yes_no', 'single_choice', 'multiple_choice', 'number'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_screening_questions');
    }
};
