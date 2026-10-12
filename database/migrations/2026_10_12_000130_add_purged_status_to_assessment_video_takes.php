<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A take whose retention period is over keeps its row (the score and the answer still point to it) but not its file.
        $this->replaceStatusCheck("'recording', 'complete', 'rejected', 'purged'");
    }

    public function down(): void
    {
        $this->replaceStatusCheck("'recording', 'complete', 'rejected'");
    }

    private function replaceStatusCheck(string $statuses): void
    {
        $connection = Schema::getConnection();
        $name = $connection->getTablePrefix().'assessment_video_takes';

        $connection->statement("ALTER TABLE \"{$name}\" DROP CONSTRAINT \"{$name}_status_check\"");
        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_status_check\" CHECK (\"status\" IN ({$statuses}))");
    }
};
