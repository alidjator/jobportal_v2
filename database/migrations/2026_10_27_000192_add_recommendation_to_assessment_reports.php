<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-tools.md` section 4: a structured recommendation on every report, kept in its revisions, and the rule that works it out for the reports a battery issues
 * automatically (the key scales and two standard-score thresholds; bands have no order, so the rule reads the standard scores of the norm table).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = Schema::getConnection()->getTablePrefix();

        Schema::table('assessment_reports', function (Blueprint $table): void {
            $table->string('recommendation', 12)->nullable();
        });

        Schema::table('assessment_report_revisions', function (Blueprint $table): void {
            $table->string('recommendation', 12)->nullable();
        });

        foreach (['assessment_reports', 'assessment_report_revisions'] as $name) {
            $table = $prefix.$name;
            Schema::getConnection()->statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$table}_recommendation_check\" CHECK (\"recommendation\" IS NULL OR \"recommendation\" IN ('recommended', 'consider', 'not_yet'))");
        }

        Schema::create('assessment_recommendation_rules', function (Blueprint $table): void {
            $table->foreignId('test_id')->primary()->constrained('assessment_tests')->cascadeOnDelete();
            $table->jsonb('key_scales');
            $table->decimal('recommended_min', 6, 2);
            $table->decimal('not_yet_below', 6, 2);
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_recommendation_rules');

        Schema::table('assessment_report_revisions', function (Blueprint $table): void {
            $table->dropColumn('recommendation');
        });

        Schema::table('assessment_reports', function (Blueprint $table): void {
            $table->dropColumn('recommendation');
        });
    }
};
