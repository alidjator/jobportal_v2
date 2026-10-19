<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-applicant-search.md` section 1: one row per application with what the lists search and filter on, copied from the profile snapshot when the
 * application is made. It holds no contact data and no protected attribute; anonymisation deletes the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = Schema::getConnection()->getTablePrefix();

        Schema::create('recruitment_application_facets', function (Blueprint $table) use ($prefix): void {
            $table->unsignedBigInteger('application_id')->primary();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->foreignId('job_id')->constrained('job_jobs')->restrictOnDelete();
            $table->timestamp('applied_at');
            $table->foreignId('province_id')->nullable()->constrained('reference_provinces')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('reference_cities')->restrictOnDelete();
            // The rank of the highest education level (`reference_education_levels.rank`).
            $table->unsignedSmallInteger('education_rank')->nullable();
            $table->unsignedInteger('experience_months')->default(0);
            // Only an expected salary given per month is kept; an hourly or per-project amount cannot be compared with it.
            $table->unsignedBigInteger('expected_salary_monthly')->nullable();
            $table->text('search_text')->default('');
            $table->boolean('from_invitation')->default(false);
            $table->boolean('returning')->default(false);

            $table->foreign('application_id', $prefix.'recruitment_facets_application_foreign')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->index(['company_id', 'applied_at'], 'recruitment_facets_company_time_index');
            $table->index('job_id', 'recruitment_facets_job_index');
        });

        $connection = Schema::getConnection();
        $table = $prefix.'recruitment_application_facets';

        // Laravel has no array column or GIN index, so both are added in SQL; every skill asked for must be in the array (`@>`).
        $connection->statement("ALTER TABLE \"{$table}\" ADD COLUMN \"skill_ids\" integer[] NOT NULL DEFAULT '{}'");
        $connection->statement("CREATE INDEX \"{$prefix}recruitment_facets_skills_gin\" ON \"{$table}\" USING gin (\"skill_ids\")");
        $connection->statement("CREATE INDEX \"{$prefix}recruitment_facets_search_trgm\" ON \"{$table}\" USING gin (immutable_unaccent(\"search_text\") gin_trgm_ops)");
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_facets');
    }
};
