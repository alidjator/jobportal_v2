<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-requisitions.md` section 4: the approval flows of a group (the default) and of a tenant (which replaces it), one per subject type. */
return new class extends Migration
{
    public function up(): void
    {
        // The owner is a group or a company: no foreign key, since one column names either (the Approval context never reads their tables).
        Schema::create('approval_flows', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_type', 10);
            $table->unsignedBigInteger('owner_id');
            $table->string('subject_type', 100);
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'subject_type'], 'approval_flows_owner_subject_unique');
        });

        Schema::create('approval_flow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flow_id')->constrained('approval_flows')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('mode', 5);
            $table->jsonb('conditions');

            $table->unique(['flow_id', 'position'], 'approval_flow_steps_flow_position_unique');
        });

        Schema::create('approval_flow_step_approvers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('step_id')->constrained('approval_flow_steps')->cascadeOnDelete();
            $table->string('kind', 12);
            $table->string('value', 100);

            $table->unique(['step_id', 'kind', 'value'], 'approval_flow_step_approvers_unique');
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $flows = $prefix.'approval_flows';
        $steps = $prefix.'approval_flow_steps';
        $approvers = $prefix.'approval_flow_step_approvers';

        $connection->statement("ALTER TABLE \"{$flows}\" ADD CONSTRAINT \"{$flows}_owner_type_check\" CHECK (\"owner_type\" IN ('group', 'company'))");
        $connection->statement("ALTER TABLE \"{$steps}\" ADD CONSTRAINT \"{$steps}_mode_check\" CHECK (\"mode\" IN ('any', 'all'))");
        $connection->statement("ALTER TABLE \"{$approvers}\" ADD CONSTRAINT \"{$approvers}_kind_check\" CHECK (\"kind\" IN ('member', 'group_role', 'relative'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_flow_step_approvers');
        Schema::dropIfExists('approval_flow_steps');
        Schema::dropIfExists('approval_flows');
    }
};
