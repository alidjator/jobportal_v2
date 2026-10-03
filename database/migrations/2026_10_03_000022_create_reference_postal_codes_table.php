<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_postal_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained('reference_villages')->restrictOnDelete();
            $table->char('code', 5);
            $table->timestamps();

            $table->unique(['village_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_postal_codes');
    }
};
