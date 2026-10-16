<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-organization.md` sections 5 and 6: grades, unit types, cost centres, and units and positions with dated versions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('organization_groups')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedSmallInteger('rank');
            $table->string('status', 10)->default('active');
            $table->timestamps();

            $table->unique(['group_id', 'code'], 'organization_grades_group_code_unique');
        });

        Schema::create('organization_unit_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('organization_groups')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedSmallInteger('level_order');
            $table->string('status', 10)->default('active');
            $table->timestamps();

            $table->unique(['group_id', 'code'], 'organization_unit_types_group_code_unique');
        });

        Schema::create('organization_cost_centres', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('organization_legal_entities')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('status', 10)->default('active');
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code'], 'organization_cost_centres_entity_code_unique');
        });

        Schema::create('organization_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('organization_legal_entities')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('status', 10)->default('active');
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code'], 'organization_units_entity_code_unique');
        });

        Schema::create('organization_unit_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('organization_units')->restrictOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('name', 150);
            $table->foreignId('parent_unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('unit_type_id')->constrained('organization_unit_types')->restrictOnDelete();
            $table->foreignId('cost_centre_id')->nullable()->constrained('organization_cost_centres')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['unit_id', 'valid_from'], 'organization_unit_versions_unit_from_unique');
        });

        Schema::create('organization_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('organization_legal_entities')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('status', 10)->default('active');
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code'], 'organization_positions_entity_code_unique');
        });

        Schema::create('organization_position_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('position_id')->constrained('organization_positions')->restrictOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('title', 150);
            $table->foreignId('unit_id')->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('min_grade_id')->nullable()->constrained('organization_grades')->restrictOnDelete();
            $table->foreignId('max_grade_id')->nullable()->constrained('organization_grades')->restrictOnDelete();
            $table->foreignId('reports_to_position_id')->nullable()->constrained('organization_positions')->restrictOnDelete();
            $table->foreignId('job_role_id')->nullable()->constrained('reference_job_roles')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['position_id', 'valid_from'], 'organization_position_versions_position_from_unique');
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        foreach (['grades', 'unit_types', 'cost_centres', 'units', 'positions'] as $name) {
            $table = $prefix.'organization_'.$name;
            $connection->statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$table}_status_check\" CHECK (\"status\" IN ('active', 'inactive'))");
        }

        foreach (['unit_versions', 'position_versions'] as $name) {
            $table = $prefix.'organization_'.$name;
            $connection->statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$table}_period_check\" CHECK (\"valid_to\" IS NULL OR \"valid_to\" >= \"valid_from\")");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_position_versions');
        Schema::dropIfExists('organization_positions');
        Schema::dropIfExists('organization_unit_versions');
        Schema::dropIfExists('organization_units');
        Schema::dropIfExists('organization_cost_centres');
        Schema::dropIfExists('organization_unit_types');
        Schema::dropIfExists('organization_grades');
    }
};
