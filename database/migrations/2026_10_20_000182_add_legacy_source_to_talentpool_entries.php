<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-activation.md` sections 9 and 11: a talent pool entry may come from the karir.pkp import (`legacy`), a private list of the company that the candidate can remove
 * themselves from. The check constraint of the `source` column gains the value.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replaceCheck("'search', 'application', 'legacy'");
    }

    public function down(): void
    {
        $this->replaceCheck("'search', 'application'");
    }

    private function replaceCheck(string $values): void
    {
        $connection = Schema::getConnection();
        $table = $connection->getTablePrefix().'talentpool_entries';

        $connection->statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$table}_source_check\"");
        $connection->statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$table}_source_check\" CHECK (\"source\" IN ({$values}))");
    }
};
