<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A test sent to a candidate for one application. The company, job and candidate are copied from the application so the invitation can be
        // read without Recruitment's tables. An application has at most one active invitation (invited or started), enforced by a partial unique index.
        Schema::create('assessment_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('recruitment_applications')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->foreignId('job_id')->constrained('job_jobs')->restrictOnDelete();
            $table->foreignId('applicant_user_id')->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('test_id')->constrained('assessment_tests')->restrictOnDelete();
            $table->foreignId('invited_by_user_id')->constrained('identity_users')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('due_at');
            $table->string('status', 20)->default('invited');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index(['applicant_user_id', 'status']);
            $table->index(['status', 'due_at']);
            $table->index('test_id');
        });

        // The company members who score the long-text answers of the invitation.
        Schema::create('assessment_invitation_assessors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invitation_id')->constrained('assessment_invitations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();

            $table->unique(['invitation_id', 'user_id']);
        });

        $connection = Schema::getConnection();
        $name = $connection->getTablePrefix().'assessment_invitations';

        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_status_check\" CHECK (\"status\" IN ('invited', 'started', 'submitted', 'expired', 'cancelled'))");
        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_window_check\" CHECK (\"due_at\" > \"starts_at\")");
        $connection->statement("CREATE UNIQUE INDEX \"{$name}_active_unique\" ON \"{$name}\" (\"application_id\") WHERE \"status\" IN ('invited', 'started')");
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_invitation_assessors');
        Schema::dropIfExists('assessment_invitations');
    }
};
