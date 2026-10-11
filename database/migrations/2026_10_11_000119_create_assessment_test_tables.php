<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A test: a skill test of a company (`company_id` set), a skill test of the system library (neither owner) or a psychological battery
        // of a provider (`provider_id` set). It is created by a company member or a psychologist (`created_by_user_id`) or by an admin
        // (`created_by_admin_id`), exactly one of the two.
        Schema::create('assessment_tests', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->foreignId('company_id')->nullable()->constrained('company_companies')->restrictOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('assessment_providers')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->jsonb('measured_capabilities');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('identity_admins')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['provider_id', 'status']);
            $table->index(['kind', 'status']);
        });

        Schema::create('assessment_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_id')->constrained('assessment_tests')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->string('format', 20);
            $table->unsignedInteger('duration_seconds');
            $table->boolean('allow_skip')->default(true);
            $table->boolean('allow_back')->default(true);
            $table->boolean('allow_jump')->default(true);
            $table->unsignedSmallInteger('draw_count')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            $table->index(['test_id', 'position']);
        });

        Schema::create('assessment_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained('assessment_sections')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 20);
            $table->text('text');
            $table->unsignedSmallInteger('points')->default(1);
            $table->string('scale_key', 60)->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index(['section_id', 'position']);
        });

        // A choice, an accepted answer (number and short text) or a scored statement (Likert, forced choice).
        Schema::create('assessment_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('label', 500);
            $table->boolean('is_correct')->default(false);
            $table->smallInteger('score_value')->nullable();
            $table->string('scale_key', 60)->nullable();

            $table->index(['question_id', 'position']);
        });

        // Raw score range of a scale mapped to a standard score and a band, owned by the psychologist who sets the battery up.
        Schema::create('assessment_norms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_id')->constrained('assessment_tests')->cascadeOnDelete();
            $table->string('scale_key', 60);
            $table->integer('raw_min');
            $table->integer('raw_max');
            $table->decimal('standard_score', 6, 2);
            $table->string('band', 40);

            $table->index(['test_id', 'scale_key']);
        });

        Schema::create('assessment_report_templates', function (Blueprint $table): void {
            $table->foreignId('test_id')->primary()->constrained('assessment_tests')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('updated_at');
        });

        // The batteries an admin lets a company use; the company also needs the setting `assessment.psychological_enabled`.
        Schema::create('assessment_company_batteries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->foreignId('test_id')->constrained('assessment_tests')->restrictOnDelete();
            $table->foreignId('granted_by_admin_id')->constrained('identity_admins')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['company_id', 'test_id']);
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $checks = [
            'assessment_tests' => [
                'kind_check' => "\"kind\" IN ('skill', 'psychological')",
                'status_check' => "\"status\" IN ('draft', 'published', 'archived')",
                // A skill test belongs to a company or to the library; a battery belongs to a provider and to no company.
                'owner_check' => "(\"kind\" = 'skill' AND \"provider_id\" IS NULL) OR (\"kind\" = 'psychological' AND \"provider_id\" IS NOT NULL AND \"company_id\" IS NULL)",
                'creator_check' => '("created_by_user_id" IS NULL) <> ("created_by_admin_id" IS NULL)',
            ],
            'assessment_sections' => [
                'format_check' => "\"format\" IN ('questions', 'speed_columns', 'drawing')",
                'duration_check' => '"duration_seconds" > 0',
            ],
            'assessment_questions' => [
                'type_check' => "\"type\" IN ('single_choice', 'multiple_response', 'true_false', 'number', 'short_text', 'long_text', 'likert', 'forced_choice', 'drawing')",
            ],
            'assessment_norms' => [
                'range_check' => '"raw_min" <= "raw_max"',
            ],
        ];

        foreach ($checks as $table => $constraints) {
            $name = $prefix.$table;

            foreach ($constraints as $suffix => $expression) {
                $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_{$suffix}\" CHECK ({$expression})");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_company_batteries');
        Schema::dropIfExists('assessment_report_templates');
        Schema::dropIfExists('assessment_norms');
        Schema::dropIfExists('assessment_question_options');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessment_sections');
        Schema::dropIfExists('assessment_tests');
    }
};
