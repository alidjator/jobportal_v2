<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-hiring-managers.md` section 3: shortlist reviews. A recruiter asks the hiring managers of a job to review chosen applications by a due date; each reviewer
 * answers `advance`, `decline` or `maybe` per application. An answer never moves an application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_review_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->foreignId('job_id')->constrained('job_jobs')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('identity_users')->restrictOnDelete();
            $table->string('message', 1000)->nullable();
            // A date in Jakarta time.
            $table->date('due_on');
            // `open`, `complete` (every reviewer answered every active application) or `closed` (ended early).
            $table->string('status', 10)->default('open');
            $table->dateTime('reminded_at')->nullable();
            $table->dateTime('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'status'], 'recruitment_review_requests_job_status_index');
            $table->index(['status', 'due_on'], 'recruitment_review_requests_status_due_index');
            $table->index('requested_by_user_id', 'recruitment_review_requests_requester_index');
        });

        Schema::create('recruitment_review_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('recruitment_review_requests')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('recruitment_applications')->cascadeOnDelete();

            $table->unique(['request_id', 'application_id'], 'recruitment_review_request_items_pair_unique');
            $table->index('application_id', 'recruitment_review_request_items_application_index');
        });

        Schema::create('recruitment_review_request_reviewers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('recruitment_review_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();

            $table->unique(['request_id', 'user_id'], 'recruitment_review_request_reviewers_pair_unique');
            $table->index('user_id', 'recruitment_review_request_reviewers_user_index');
        });

        Schema::create('recruitment_application_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('recruitment_review_requests')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('recruitment_applications')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('identity_users')->restrictOnDelete();
            // `advance`, `decline` or `maybe`.
            $table->string('decision', 10);
            // Required for `decline`; cleared when the applicant is erased (the decision stays).
            $table->string('reason', 1000)->nullable();
            $table->dateTime('answered_at');
            $table->dateTime('updated_at');

            $table->unique(['request_id', 'application_id', 'reviewer_user_id'], 'recruitment_application_reviews_triple_unique');
            $table->index('application_id', 'recruitment_application_reviews_application_index');
            $table->index('reviewer_user_id', 'recruitment_application_reviews_reviewer_index');
        });

        $prefix = Schema::getConnection()->getTablePrefix();

        DB::statement("ALTER TABLE \"{$prefix}recruitment_review_requests\" ADD CONSTRAINT \"{$prefix}recruitment_review_requests_status_check\" CHECK (\"status\" IN ('open', 'complete', 'closed'))");
        DB::statement("ALTER TABLE \"{$prefix}recruitment_application_reviews\" ADD CONSTRAINT \"{$prefix}recruitment_application_reviews_decision_check\" CHECK (\"decision\" IN ('advance', 'decline', 'maybe'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_reviews');
        Schema::dropIfExists('recruitment_review_request_reviewers');
        Schema::dropIfExists('recruitment_review_request_items');
        Schema::dropIfExists('recruitment_review_requests');
    }
};
