<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained('reference_provinces')->restrictOnDelete();
            $table->string('bps_code', 10)->unique();
            $table->string('name', 150);
            $table->string('type', 10);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'reference_cities';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_type_check` CHECK (`type` IN ('city', 'regency'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_cities');
    }
};
