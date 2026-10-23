<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-assessment-automation.md` sections 1 and 2: the default test of a job for one of its company's assessment stages, with the rule that advances an
 * application automatically, and the stage an invitation was sent in (the advance only applies to the stage it was sent for).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_job_stage_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_id')->constrained('job_jobs')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('recruitment_stages')->cascadeOnDelete();
            $table->foreignId('test_id')->constrained('assessment_tests')->restrictOnDelete();
            $table->unsignedSmallInteger('due_days')->default(3);
            $table->unsignedSmallInteger('advance_min_percentage')->nullable();
            // The stage an application moves to; no foreign key, because deleting a stage must not be blocked, and the advance checks that the stage still exists.
            $table->unsignedBigInteger('advance_to_stage_id')->nullable()->index();
            $table->foreignId('updated_by')->constrained('identity_users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['job_id', 'stage_id'], 'assessment_job_stage_tests_job_stage_unique');
            $table->index('stage_id', 'assessment_job_stage_tests_stage_index');
            $table->index('test_id', 'assessment_job_stage_tests_test_index');
            $table->index('updated_by', 'assessment_job_stage_tests_updated_by_index');
        });

        Schema::create('assessment_job_stage_test_assessors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('default_id')->constrained('assessment_job_stage_tests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();

            $table->unique(['default_id', 'user_id'], 'assessment_job_stage_test_assessors_pair_unique');
            $table->index('user_id', 'assessment_job_stage_test_assessors_user_index');
        });

        $connection = Schema::getConnection();
        $name = $connection->getTablePrefix().'assessment_job_stage_tests';

        // Both parts of the advance rule or neither.
        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_advance_check\" CHECK ((\"advance_min_percentage\" IS NULL) = (\"advance_to_stage_id\" IS NULL))");
        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_percentage_check\" CHECK (\"advance_min_percentage\" IS NULL OR \"advance_min_percentage\" BETWEEN 1 AND 100)");

        Schema::table('assessment_invitations', function (Blueprint $table): void {
            $table->foreignId('stage_id')->nullable()->constrained('recruitment_stages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stage_id');
        });

        Schema::dropIfExists('assessment_job_stage_test_assessors');
        Schema::dropIfExists('assessment_job_stage_tests');
    }
};
