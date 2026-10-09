<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trigram indexes on the exact expression `TextSearch` filters by (`immutable_unaccent(column)`), so a search
     * for part of a word does not read the whole table. Columns searched on small tables only (reference data) stay unindexed.
     */
    private const array COLUMNS = [
        'job_jobs' => ['title'],
        'company_companies' => ['name', 'slug'],
        'jobseeker_profiles' => ['headline', 'summary'],
        'jobseeker_experiences' => ['job_title'],
        'recruitment_screening_questions' => ['text'],
    ];

    public function up(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $connection->statement(
                    "CREATE INDEX \"{$prefix}{$table}_{$column}_trgm\" ON \"{$prefix}{$table}\" USING gin (immutable_unaccent(\"{$column}\") gin_trgm_ops)"
                );
            }
        }
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $connection->statement("DROP INDEX IF EXISTS \"{$prefix}{$table}_{$column}_trgm\"");
            }
        }
    }
};
