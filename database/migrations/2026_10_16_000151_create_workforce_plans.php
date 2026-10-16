<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-requisitions.md` sections 2 and 5: the manpower plans of a legal entity, one per fiscal year in versions, with a line per position and its additions per month. */
return new class extends Migration
{
    public function up(): void
    {
        // The legal entity, the positions and the approval belong to other contexts and are plain columns (a position is deactivated, never deleted).
        Schema::create('workforce_plans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedSmallInteger('version');
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->foreignId('created_by_id')->constrained('identity_users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'fiscal_year', 'version'], 'workforce_plans_entity_year_version_unique');
        });

        Schema::create('workforce_plan_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('workforce_plans')->cascadeOnDelete();
            $table->unsignedBigInteger('position_id');
            $table->unsignedInteger('opening_headcount');
            $table->unsignedInteger('target_headcount');
            $table->unsignedBigInteger('estimated_annual_cost')->nullable();

            $table->unique(['plan_id', 'position_id'], 'workforce_plan_lines_plan_position_unique');
            $table->index('position_id', 'workforce_plan_lines_position_index');
        });

        Schema::create('workforce_plan_line_months', function (Blueprint $table): void {
            $table->foreignId('line_id')->constrained('workforce_plan_lines')->cascadeOnDelete();
            $table->unsignedSmallInteger('month');
            $table->unsignedInteger('additions');

            $table->primary(['line_id', 'month']);
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $plans = $prefix.'workforce_plans';
        $months = $prefix.'workforce_plan_line_months';

        $connection->statement("ALTER TABLE \"{$plans}\" ADD CONSTRAINT \"{$plans}_status_check\" CHECK (\"status\" IN ('draft', 'pending_approval', 'approved', 'rejected', 'superseded'))");
        $connection->statement("ALTER TABLE \"{$months}\" ADD CONSTRAINT \"{$months}_month_check\" CHECK (\"month\" BETWEEN 1 AND 12)");
        // One version is in force and one is being worked on (a draft, one waiting for a decision or a rejected one) per legal entity and fiscal year.
        $connection->statement("CREATE UNIQUE INDEX \"{$plans}_in_force_unique\" ON \"{$plans}\" (\"legal_entity_id\", \"fiscal_year\") WHERE \"status\" = 'approved'");
        $connection->statement("CREATE UNIQUE INDEX \"{$plans}_open_unique\" ON \"{$plans}\" (\"legal_entity_id\", \"fiscal_year\") WHERE \"status\" IN ('draft', 'pending_approval', 'rejected')");
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_plan_line_months');
        Schema::dropIfExists('workforce_plan_lines');
        Schema::dropIfExists('workforce_plans');
    }
};
