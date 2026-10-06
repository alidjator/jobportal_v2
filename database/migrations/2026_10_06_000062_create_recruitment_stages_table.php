<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->string('type', 20);
            // The case-insensitive collation makes the unique key below a case-insensitive "unique name per company" rule.
            $table->string('name', 60)->collation('utf8mb4_unicode_ci');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_default')->default(false);
            // The type of a default stage, NULL for an extra one: one default per type and company is guarded by the unique key.
            $table->string('default_type', 20)->nullable()->virtualAs('CASE WHEN `is_default` = 1 THEN `type` ELSE NULL END');
            $table->timestamps();

            $table->unique(['company_id', 'name'], 'recruitment_stages_company_name_unique');
            $table->unique(['company_id', 'default_type'], 'recruitment_stages_company_default_unique');
            $table->index(['company_id', 'position'], 'recruitment_stages_company_position_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'recruitment_stages';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_type_check` CHECK (`type` IN ('applied', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_stages');
    }
};
