<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_subdistricts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->constrained('reference_cities')->restrictOnDelete();
            $table->string('bps_code', 10)->unique();
            $table->string('name', 150);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_subdistricts');
    }
};
