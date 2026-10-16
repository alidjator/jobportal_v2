<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-requisitions.md` section 4: running approvals (the flow copied at submission), their steps and approver slots, and the delegations. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_instances', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->unsignedBigInteger('group_id');
            $table->foreignId('requester_id')->constrained('identity_users')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('status', 10);
            $table->jsonb('facts');
            $table->string('outcome_reason', 500)->nullable();
            $table->foreignId('outcome_by_user_id')->nullable()->constrained('identity_users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'approval_instances_subject_index');
            $table->index(['company_id', 'status'], 'approval_instances_company_status_index');
            $table->index(['requester_id', 'status'], 'approval_instances_requester_status_index');
        });

        Schema::create('approval_instance_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('approval_instances')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('mode', 5);
            $table->string('status', 10);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unique(['instance_id', 'position'], 'approval_instance_steps_position_unique');
        });

        // A slot is one place to fill in a step: a named account, or any holder of a group permission. A slot whose account left the tenant is `vacant` until replaced.
        Schema::create('approval_instance_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('step_id')->constrained('approval_instance_steps')->cascadeOnDelete();
            $table->string('kind', 5);
            $table->foreignId('user_id')->nullable()->constrained('identity_users')->restrictOnDelete();
            $table->string('permission', 40)->nullable();
            $table->string('status', 10);
            $table->foreignId('decided_by_user_id')->nullable()->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('on_behalf_of_user_id')->nullable()->constrained('identity_users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->index(['user_id', 'status'], 'approval_instance_slots_user_status_index');
        });

        Schema::create('approval_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approver_user_id')->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('delegate_user_id')->constrained('identity_users')->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->timestamp('created_at')->nullable();

            $table->index(['delegate_user_id', 'from_date', 'to_date'], 'approval_delegations_delegate_index');
            $table->index('approver_user_id', 'approval_delegations_approver_index');
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $instances = $prefix.'approval_instances';
        $steps = $prefix.'approval_instance_steps';
        $slots = $prefix.'approval_instance_slots';
        $delegations = $prefix.'approval_delegations';

        $connection->statement("ALTER TABLE \"{$instances}\" ADD CONSTRAINT \"{$instances}_status_check\" CHECK (\"status\" IN ('pending', 'approved', 'rejected', 'returned', 'cancelled'))");
        // One running approval per subject: a subject goes through approval again only after the previous one ended.
        $connection->statement("CREATE UNIQUE INDEX \"{$instances}_one_pending_unique\" ON \"{$instances}\" (\"subject_type\", \"subject_id\") WHERE \"status\" = 'pending'");
        $connection->statement("ALTER TABLE \"{$steps}\" ADD CONSTRAINT \"{$steps}_status_check\" CHECK (\"status\" IN ('waiting', 'pending', 'approved'))");
        $connection->statement("ALTER TABLE \"{$steps}\" ADD CONSTRAINT \"{$steps}_mode_check\" CHECK (\"mode\" IN ('any', 'all'))");
        $connection->statement("ALTER TABLE \"{$slots}\" ADD CONSTRAINT \"{$slots}_kind_check\" CHECK (\"kind\" IN ('user', 'role'))");
        $connection->statement("ALTER TABLE \"{$slots}\" ADD CONSTRAINT \"{$slots}_status_check\" CHECK (\"status\" IN ('open', 'approved', 'vacant'))");
        $connection->statement("ALTER TABLE \"{$delegations}\" ADD CONSTRAINT \"{$delegations}_range_check\" CHECK (\"to_date\" >= \"from_date\")");
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_instance_slots');
        Schema::dropIfExists('approval_instance_steps');
        Schema::dropIfExists('approval_instances');
    }
};
