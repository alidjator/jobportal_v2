<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-platform-admin.md` sections 1 and 3: job reports with the hold column, and the name change requests of a verified company. */
return new class extends Migration
{
    public function up(): void
    {
        // Set once when enough distinct job seekers reported the job, cleared when an admin decides.
        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->timestamp('report_hold_at')->nullable();
        });

        Schema::create('job_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_id')->constrained('job_jobs')->cascadeOnDelete();
            // A plain column, not a key: the account erasure clears it (and the note) while the report stays.
            $table->unsignedBigInteger('reporter_user_id')->nullable();
            $table->string('reason', 30);
            $table->string('note', 1000)->nullable();
            $table->string('status', 10)->default('open');
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('identity_admins')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'status'], 'job_reports_job_status_index');
            $table->index(['status', 'created_at'], 'job_reports_status_created_index');
            $table->index('reporter_user_id', 'job_reports_reporter_index');
        });

        Schema::create('company_name_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('identity_users')->restrictOnDelete();
            $table->string('current_name', 255);
            $table->string('new_name', 255);
            $table->string('reason', 1000);
            // Deleted 90 days after the decision, so the column is cleared then.
            $table->string('document_path', 255)->nullable();
            $table->string('document_name', 255)->nullable();
            $table->string('status', 10)->default('pending');
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('identity_admins')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'company_name_changes_status_created_index');
            $table->index(['company_id', 'created_at'], 'company_name_changes_company_created_index');
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $reports = $prefix.'job_reports';
        $changes = $prefix.'company_name_change_requests';

        $connection->statement("ALTER TABLE \"{$reports}\" ADD CONSTRAINT \"{$reports}_reason_check\" CHECK (\"reason\" IN ('fee_requested', 'fake_or_misleading', 'discriminatory', 'spam_or_duplicate', 'inappropriate', 'false_accessibility_claim', 'other'))");
        $connection->statement("ALTER TABLE \"{$reports}\" ADD CONSTRAINT \"{$reports}_status_check\" CHECK (\"status\" IN ('open', 'dismissed', 'actioned'))");
        // One open report per reporter and job; a cleared reporter (erased account) never collides.
        $connection->statement("CREATE UNIQUE INDEX \"job_reports_open_unique\" ON \"{$reports}\" (\"job_id\", \"reporter_user_id\") WHERE \"status\" = 'open'");

        $connection->statement("ALTER TABLE \"{$changes}\" ADD CONSTRAINT \"{$changes}_status_check\" CHECK (\"status\" IN ('pending', 'approved', 'rejected', 'cancelled'))");
        // One pending request per company.
        $connection->statement("CREATE UNIQUE INDEX \"company_name_changes_pending_unique\" ON \"{$changes}\" (\"company_id\") WHERE \"status\" = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('company_name_change_requests');
        Schema::dropIfExists('job_reports');

        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->dropColumn('report_hold_at');
        });
    }
};
