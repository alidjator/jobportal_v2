<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A job seeker's review of a company they applied to: one per person per company. The title and body are null once the author's account was erased.
        Schema::create('review_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('identity_users')->restrictOnDelete();
            $table->unsignedSmallInteger('rating');
            $table->unsignedSmallInteger('culture_rating')->nullable();
            $table->unsignedSmallInteger('pay_rating')->nullable();
            $table->unsignedSmallInteger('hiring_rating')->nullable();
            $table->string('title', 100)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20);
            $table->string('reason', 30)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('moderated_by_admin_id')->nullable()->constrained('identity_admins')->restrictOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id'], 'review_reviews_company_user_unique');
            $table->index(['company_id', 'status', 'published_at'], 'review_reviews_public_index');
            $table->index(['status', 'created_at'], 'review_reviews_queue_index');
            $table->index('user_id', 'review_reviews_user_index');
        });

        // The company's one reply to a review.
        Schema::create('review_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained('review_reviews')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20);
            $table->string('reason', 30)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('written_by_user_id')->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('moderated_by_admin_id')->nullable()->constrained('identity_admins')->restrictOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique('review_id', 'review_replies_review_unique');
            $table->index(['status', 'created_at'], 'review_replies_queue_index');
        });

        // A report of a published review by a job seeker (reporter_id is the account) or by the company (reporter_id is the company); a plain column on purpose.
        Schema::create('review_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained('review_reviews')->cascadeOnDelete();
            $table->string('reporter_kind', 10);
            $table->unsignedBigInteger('reporter_id');
            $table->string('reason', 30);
            $table->string('note', 500)->nullable();
            $table->string('status', 20);
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('identity_admins')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'review_reports_queue_index');
            $table->index(['reporter_kind', 'reporter_id'], 'review_reports_reporter_index');
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $reviews = $prefix.'review_reviews';
        $replies = $prefix.'review_replies';
        $reports = $prefix.'review_reports';
        $reasons = "'spam', 'abusive', 'not-about-company', 'personal-data', 'false-claim', 'other'";

        $connection->statement("ALTER TABLE \"{$reviews}\" ADD CONSTRAINT \"{$reviews}_status_check\" CHECK (\"status\" IN ('pending', 'published', 'rejected', 'removed'))");
        $connection->statement("ALTER TABLE \"{$reviews}\" ADD CONSTRAINT \"{$reviews}_rating_check\" CHECK (\"rating\" BETWEEN 1 AND 5)");
        foreach (['culture_rating', 'pay_rating', 'hiring_rating'] as $aspect) {
            $connection->statement("ALTER TABLE \"{$reviews}\" ADD CONSTRAINT \"{$reviews}_{$aspect}_check\" CHECK (\"{$aspect}\" IS NULL OR \"{$aspect}\" BETWEEN 1 AND 5)");
        }
        $connection->statement("ALTER TABLE \"{$reviews}\" ADD CONSTRAINT \"{$reviews}_reason_check\" CHECK (\"reason\" IS NULL OR \"reason\" IN ({$reasons}))");
        $connection->statement("ALTER TABLE \"{$replies}\" ADD CONSTRAINT \"{$replies}_status_check\" CHECK (\"status\" IN ('pending', 'published', 'rejected'))");
        $connection->statement("ALTER TABLE \"{$replies}\" ADD CONSTRAINT \"{$replies}_reason_check\" CHECK (\"reason\" IS NULL OR \"reason\" IN ({$reasons}))");
        $connection->statement("ALTER TABLE \"{$reports}\" ADD CONSTRAINT \"{$reports}_status_check\" CHECK (\"status\" IN ('open', 'dismissed', 'actioned'))");
        $connection->statement("ALTER TABLE \"{$reports}\" ADD CONSTRAINT \"{$reports}_kind_check\" CHECK (\"reporter_kind\" IN ('user', 'company'))");
        $connection->statement("ALTER TABLE \"{$reports}\" ADD CONSTRAINT \"{$reports}_reason_check\" CHECK (\"reason\" IN ('spam', 'abusive', 'false-claim', 'personal-data', 'other'))");

        // One open report per reporter per review; a resolved one does not stop the same reporter from reporting again.
        $connection->statement("CREATE UNIQUE INDEX \"review_reports_open_unique\" ON \"{$reports}\" (\"review_id\", \"reporter_kind\", \"reporter_id\") WHERE \"status\" = 'open'");
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_replies');
        Schema::dropIfExists('review_reviews');
    }
};
