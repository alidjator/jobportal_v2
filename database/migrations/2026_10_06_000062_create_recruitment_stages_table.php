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
            $table->string('name', 60);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'position'], 'recruitment_stages_company_position_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'recruitment_stages';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_type_check\" CHECK (\"type\" IN ('applied', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'))"
        );

        // A stage name is unique per company ignoring case.
        $connection->statement(
            "CREATE UNIQUE INDEX \"recruitment_stages_company_name_unique\" ON \"{$tableName}\" (\"company_id\", lower(\"name\"))"
        );
        // One default stage per type and company; the extra stages (is_default false) are not part of the index.
        $connection->statement(
            "CREATE UNIQUE INDEX \"recruitment_stages_company_default_unique\" ON \"{$tableName}\" (\"company_id\", \"type\") WHERE \"is_default\""
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_stages');
    }
};
