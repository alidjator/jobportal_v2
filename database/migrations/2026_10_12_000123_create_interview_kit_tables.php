<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interview kits (`docs/design/phase-7-interview-kits.md`, part 1): the company library, the default kit per job and interview stage, the panel of an
 * interview (for every format) and the copy of a kit's questions that each interview keeps. The scorecard tables come with part 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_interview_kits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->string('name', 100);
            $table->foreignId('created_by')->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('identity_users')->restrictOnDelete();
            $table->timestamps();

            $table->index('created_by', 'recruitment_interview_kits_created_by_index');
            $table->index('updated_by', 'recruitment_interview_kits_updated_by_index');
        });

        Schema::create('recruitment_interview_kit_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kit_id')->constrained('recruitment_interview_kits')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('text', 500);
            // Shown to the interviewers only, never to the job seeker.
            $table->string('guidance', 1000)->nullable();
            $table->unsignedTinyInteger('weight');

            $table->unique(['kit_id', 'position'], 'recruitment_interview_kit_questions_position_unique');
        });

        // A job's default kit for one of its company's interview stages. The rows go away with the job, the stage or the kit.
        Schema::create('recruitment_job_stage_kits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_id')->constrained('job_jobs')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('recruitment_stages')->cascadeOnDelete();
            $table->foreignId('kit_id')->constrained('recruitment_interview_kits')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['job_id', 'stage_id'], 'recruitment_job_stage_kits_job_stage_unique');
            $table->index('stage_id', 'recruitment_job_stage_kits_stage_index');
            $table->index('kit_id', 'recruitment_job_stage_kits_kit_index');
        });

        // The extra members of an interview's panel; the main interviewer stays in `recruitment_interviews.interviewer_id`.
        Schema::create('recruitment_interview_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')->constrained('recruitment_interviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();

            $table->unique(['interview_id', 'user_id'], 'recruitment_interview_participants_pair_unique');
            $table->index('user_id', 'recruitment_interview_participants_user_index');
        });

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            // For information only: the kit the questions were copied from. Deleting the kit leaves the interview and its copy unchanged.
            $table->foreignId('kit_id')->nullable()->constrained('recruitment_interview_kits')->nullOnDelete();
            $table->string('kit_name', 100)->nullable();
        });

        Schema::create('recruitment_interview_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')->constrained('recruitment_interviews')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('text', 500);
            $table->string('guidance', 1000)->nullable();
            $table->unsignedTinyInteger('weight');

            $table->unique(['interview_id', 'position'], 'recruitment_interview_questions_position_unique');
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $connection->statement("CREATE UNIQUE INDEX \"recruitment_interview_kits_company_name_unique\" ON \"{$prefix}recruitment_interview_kits\" (\"company_id\", lower(\"name\"))");

        foreach (['recruitment_interview_kit_questions', 'recruitment_interview_questions'] as $table) {
            $connection->statement("ALTER TABLE \"{$prefix}{$table}\" ADD CONSTRAINT \"{$prefix}{$table}_weight_check\" CHECK (\"weight\" BETWEEN 1 AND 5)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_interview_questions');

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('kit_id');
            $table->dropColumn('kit_name');
        });

        Schema::dropIfExists('recruitment_interview_participants');
        Schema::dropIfExists('recruitment_job_stage_kits');
        Schema::dropIfExists('recruitment_interview_kit_questions');
        Schema::dropIfExists('recruitment_interview_kits');
    }
};
