<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_tags', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('slug', 150)->unique();
            $table->string('popularity_override', 10)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'reference_tags';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_popularity_override_check` CHECK (`popularity_override` IN ('pinned', 'hidden'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_tags');
    }
};
