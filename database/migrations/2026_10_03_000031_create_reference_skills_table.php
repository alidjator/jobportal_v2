<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_skills', function (Blueprint $table): void {
            $table->id();
            $table->jsonb('name');
            $table->string('slug', 150)->unique();
            $table->string('kind', 10);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'reference_skills';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_kind_check\" CHECK (\"kind\" IN ('hard', 'soft'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_skills');
    }
};
