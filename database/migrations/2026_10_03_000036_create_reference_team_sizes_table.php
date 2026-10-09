<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_team_sizes', function (Blueprint $table): void {
            $table->id();
            $table->jsonb('name');
            $table->string('slug', 150)->unique();
            $table->integer('min_size')->nullable();
            $table->integer('max_size')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_team_sizes');
    }
};
