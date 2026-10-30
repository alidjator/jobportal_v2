<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string BASE = "'qualifications_mismatch', 'experience_insufficient', 'salary_expectation_mismatch', 'location_mismatch', 'failed_screening', 'position_filled', 'other'";

    public function up(): void
    {
        $this->replaceConstraint(self::BASE.", 'failed_pre_employment_check'");
    }

    public function down(): void
    {
        $this->replaceConstraint(self::BASE);
    }

    private function replaceConstraint(string $reasons): void
    {
        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'recruitment_application_stage_history';

        $connection->statement("ALTER TABLE \"{$tableName}\" DROP CONSTRAINT \"{$tableName}_reason_check\"");
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_reason_check\" CHECK (\"rejection_reason\" IS NULL OR \"rejection_reason\" IN ({$reasons}))");
    }
};
