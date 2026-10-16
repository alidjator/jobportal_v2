<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-requisitions.md` sections 1, 3 and 5: requisitions and their amendments, and the link from a job to the requisition it was opened for. */
return new class extends Migration
{
    public function up(): void
    {
        // The legal entity, position, unit, grade, cost centre and approval belong to other contexts and are plain columns (structure items are deactivated, never deleted).
        Schema::create('workforce_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->unsignedBigInteger('legal_entity_id');
            $table->string('type', 12);
            $table->string('status', 20);
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('requested_grade_id')->nullable();
            $table->unsignedSmallInteger('hires');
            $table->foreignId('job_type_id')->constrained('reference_job_types')->restrictOnDelete();
            $table->unsignedSmallInteger('contract_months')->nullable();
            $table->date('target_join_date');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('cost_centre_id')->nullable();
            $table->unsignedBigInteger('salary_min')->nullable();
            $table->unsignedBigInteger('salary_max')->nullable();
            $table->string('justification', 2000);
            $table->string('replacement_name', 150)->nullable();
            $table->date('replacement_leaving_date')->nullable();
            $table->foreignId('hiring_manager_user_id')->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('identity_users')->restrictOnDelete();
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->string('closed_reason', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'workforce_requisitions_company_status_index');
            $table->index(['legal_entity_id', 'position_id', 'fiscal_year'], 'workforce_requisitions_quota_index');
        });

        // An amendment raises the hires or the budget of an approved requisition and goes through approval; the new values replace the old ones when it is approved.
        Schema::create('workforce_requisition_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requisition_id')->constrained('workforce_requisitions')->cascadeOnDelete();
            $table->unsignedSmallInteger('hires')->nullable();
            $table->unsignedBigInteger('salary_min')->nullable();
            $table->unsignedBigInteger('salary_max')->nullable();
            $table->string('reason', 500);
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->foreignId('requested_by_id')->constrained('identity_users')->restrictOnDelete();
            $table->string('decision_reason', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['requisition_id', 'status'], 'workforce_requisition_amendments_status_index');
        });

        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->unsignedBigInteger('requisition_id')->nullable()->index();
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $requisitions = $prefix.'workforce_requisitions';
        $amendments = $prefix.'workforce_requisition_amendments';

        $connection->statement("ALTER TABLE \"{$requisitions}\" ADD CONSTRAINT \"{$requisitions}_type_check\" CHECK (\"type\" IN ('planned', 'replacement', 'unplanned'))");
        $connection->statement("ALTER TABLE \"{$requisitions}\" ADD CONSTRAINT \"{$requisitions}_status_check\" CHECK (\"status\" IN ('draft', 'pending_approval', 'approved', 'in_recruitment', 'filled', 'closed', 'rejected', 'cancelled'))");
        $connection->statement("ALTER TABLE \"{$amendments}\" ADD CONSTRAINT \"{$amendments}_status_check\" CHECK (\"status\" IN ('pending_approval', 'approved', 'rejected', 'returned', 'withdrawn'))");
        // One amendment waits for a decision at a time.
        $connection->statement("CREATE UNIQUE INDEX \"{$amendments}_pending_unique\" ON \"{$amendments}\" (\"requisition_id\") WHERE \"status\" = 'pending_approval'");
    }

    public function down(): void
    {
        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->dropColumn('requisition_id');
        });

        Schema::dropIfExists('workforce_requisition_amendments');
        Schema::dropIfExists('workforce_requisitions');
    }
};
