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

        Schema::create('recruitment_application_stage_history', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            // Plain columns, no foreign key: an extra stage can be deleted later, and the history keeps the stage types.
            $table->unsignedBigInteger('from_stage_id')->nullable();
            $table->unsignedBigInteger('to_stage_id');
            $table->string('from_type', 20)->nullable();
            $table->string('to_type', 20);
            $table->string('actor_kind', 20);
            // The user or admin that made the move; NULL for the system. No foreign key to accounts.
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('rejection_reason', 40)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at');

            // MariaDB limits identifiers to 64 characters, so the foreign key needs an explicit name.
            $table->foreign('application_id', $prefix.'recruitment_stage_history_application_foreign')->references('id')->on('recruitment_applications')->cascadeOnDelete();

            $table->index(['application_id', 'created_at'], 'recruitment_stage_history_application_time_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'recruitment_application_stage_history';
        $types = "'applied', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'";

        $connection->statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_to_type_check` CHECK (`to_type` IN ({$types}))");
        $connection->statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_from_type_check` CHECK (`from_type` IS NULL OR `from_type` IN ({$types}))");
        $connection->statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_actor_kind_check` CHECK (`actor_kind` IN ('applicant', 'recruiter', 'system'))");
        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_reason_check` CHECK (`rejection_reason` IS NULL OR `rejection_reason` IN ('qualifications_mismatch', 'experience_insufficient', 'salary_expectation_mismatch', 'location_mismatch', 'failed_screening', 'position_filled', 'other'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_stage_history');
    }
};
