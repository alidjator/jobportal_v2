<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-job-discovery.md` sections 1 and 5: the full text vector of a job and the `shared` application source. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->addColumn('tsvector', 'search_vector')->nullable();
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $jobs = $prefix.'job_jobs';
        $companies = $prefix.'company_companies';
        $jobTags = $prefix.'job_job_tags';
        $tags = $prefix.'reference_tags';

        // Title A, tags B, company name C, description D (Markdown marks stripped), all unaccented, as `DatabaseJobSearchIndex` builds it.
        $connection->statement(<<<SQL
            UPDATE "{$jobs}" AS j SET search_vector =
                setweight(to_tsvector('simple', immutable_unaccent(j.title)), 'A')
                || setweight(to_tsvector('simple', immutable_unaccent(COALESCE((
                    SELECT string_agg(t.name->>'en', ' ') FROM "{$jobTags}" jt JOIN "{$tags}" t ON t.id = jt.tag_id WHERE jt.job_id = j.id
                ), ''))), 'B')
                || setweight(to_tsvector('simple', immutable_unaccent(COALESCE((SELECT c.name FROM "{$companies}" c WHERE c.id = j.company_id), ''))), 'C')
                || setweight(to_tsvector('simple', immutable_unaccent(regexp_replace(COALESCE(j.description, ''), '[#*_`>~\\[\\]()!|-]+', ' ', 'g'))), 'D')
            SQL);

        $connection->statement("CREATE INDEX \"job_jobs_search_vector_index\" ON \"{$jobs}\" USING GIN (\"search_vector\")");

        $applications = $prefix.'recruitment_applications';
        $connection->statement("ALTER TABLE \"{$applications}\" DROP CONSTRAINT \"{$applications}_source_check\"");
        $connection->statement("ALTER TABLE \"{$applications}\" ADD CONSTRAINT \"{$applications}_source_check\" CHECK (\"source\" IS NULL OR \"source\" IN ('search', 'recommendation', 'alert', 'direct', 'invitation', 'shared'))");
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $applications = $connection->getTablePrefix().'recruitment_applications';

        $connection->statement("UPDATE \"{$applications}\" SET source = 'direct' WHERE source = 'shared'");
        $connection->statement("ALTER TABLE \"{$applications}\" DROP CONSTRAINT \"{$applications}_source_check\"");
        $connection->statement("ALTER TABLE \"{$applications}\" ADD CONSTRAINT \"{$applications}_source_check\" CHECK (\"source\" IS NULL OR \"source\" IN ('search', 'recommendation', 'alert', 'direct', 'invitation'))");

        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->dropColumn('search_vector');
        });
    }
};
