<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string BEFORE = "'applied', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'";

    private const string AFTER = "'applied', 'screening', 'assessment', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'";

    public function up(): void
    {
        $this->replaceConstraints(self::AFTER);
    }

    public function down(): void
    {
        // Fails while a stage, application or history row of the type `assessment` exists: those rows must be moved first.
        $this->replaceConstraints(self::BEFORE);
    }

    private function replaceConstraints(string $types): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $checks = [
            ['recruitment_stages', 'type', '"type" IN (%s)'],
            ['recruitment_applications', 'stage_type', '"stage_type" IN (%s)'],
            ['recruitment_application_stage_history', 'to_type', '"to_type" IN (%s)'],
            ['recruitment_application_stage_history', 'from_type', '"from_type" IS NULL OR "from_type" IN (%s)'],
        ];

        foreach ($checks as [$table, $column, $expression]) {
            $tableName = $prefix.$table;
            $name = "{$tableName}_{$column}_check";

            $connection->statement("ALTER TABLE \"{$tableName}\" DROP CONSTRAINT \"{$name}\"");
            $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$name}\" CHECK (".sprintf($expression, $types).')');
        }
    }
};
