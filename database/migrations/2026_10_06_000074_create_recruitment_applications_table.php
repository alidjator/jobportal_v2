<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_applications', function (Blueprint $table): void {
            $table->id();
            // The applicant and the job live in other contexts: plain indexed columns, no foreign key.
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('job_id');
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('recruitment_stages')->restrictOnDelete();
            // The system type of the current stage, kept here so lists and funnels need no join.
            $table->string('stage_type', 20);
            // "<user_id>-<job_id>" while the application is not withdrawn, NULL afterwards: the unique key allows one live application per applicant and job.
            $table->string('active_key', 41)->nullable();
            $table->text('cover_letter')->nullable();
            // The application's own copy of the resume file (random path on the private disk); erased on anonymisation.
            $table->string('resume_name', 100)->nullable();
            $table->string('resume_path', 255)->nullable();
            $table->unsignedInteger('resume_size_bytes')->nullable();
            // The profile as it was when the applicant applied; erased on anonymisation.
            $table->json('profile_snapshot')->nullable();
            // NULL when the job has no must-have question.
            $table->boolean('meets_must_have')->nullable();
            $table->text('withdrawn_note')->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->unique('active_key', 'recruitment_applications_active_key_unique');
            $table->index(['job_id', 'stage_type'], 'recruitment_applications_job_stage_type_index');
            $table->index(['user_id', 'applied_at'], 'recruitment_applications_user_applied_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'recruitment_applications';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_stage_type_check` CHECK (`stage_type` IN ('applied', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_applications');
    }
};
