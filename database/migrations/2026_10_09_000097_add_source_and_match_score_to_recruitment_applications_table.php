<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table): void {
            // Where the applicant came from and how well they fitted the job when they applied. Both are set by the server; applications from before stay empty.
            $table->string('source', 15)->nullable()->after('meets_must_have');
            $table->unsignedTinyInteger('match_score')->nullable()->after('source');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'recruitment_applications';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_source_check` CHECK (`source` IS NULL OR `source` IN ('search', 'recommendation', 'alert', 'direct', 'invitation'))"
        );
        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_match_score_check` CHECK (`match_score` IS NULL OR `match_score` <= 100)"
        );
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'recruitment_applications';

        $connection->statement("ALTER TABLE `{$tableName}` DROP CONSTRAINT `{$tableName}_source_check`");
        $connection->statement("ALTER TABLE `{$tableName}` DROP CONSTRAINT `{$tableName}_match_score_check`");

        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->dropColumn(['source', 'match_score']);
        });
    }
};
