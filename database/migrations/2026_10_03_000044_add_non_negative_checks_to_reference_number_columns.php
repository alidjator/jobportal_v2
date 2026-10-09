<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** PostgreSQL has no unsigned integers, so a check constraint keeps these columns from going negative (NULL stays allowed). */
    private const array COLUMNS = [
        'reference_experience_levels' => ['min_years', 'max_years'],
        'reference_education_levels' => ['rank'],
        'reference_team_sizes' => ['min_size', 'max_size'],
    ];

    public function up(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $connection->statement("ALTER TABLE \"{$prefix}{$table}\" ADD CONSTRAINT \"{$prefix}{$table}_{$column}_check\" CHECK (\"{$column}\" >= 0)");
            }
        }
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $connection->statement("ALTER TABLE \"{$prefix}{$table}\" DROP CONSTRAINT \"{$prefix}{$table}_{$column}_check\"");
            }
        }
    }
};
