<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reference_experience_levels', function (Blueprint $table): void {
            $table->unsignedSmallInteger('min_years')->nullable()->change();
            $table->unsignedSmallInteger('max_years')->nullable()->change();
        });

        Schema::table('reference_education_levels', function (Blueprint $table): void {
            $table->unsignedSmallInteger('rank')->change();
        });

        Schema::table('reference_team_sizes', function (Blueprint $table): void {
            $table->unsignedInteger('min_size')->nullable()->change();
            $table->unsignedInteger('max_size')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reference_experience_levels', function (Blueprint $table): void {
            $table->smallInteger('min_years')->nullable()->change();
            $table->smallInteger('max_years')->nullable()->change();
        });

        Schema::table('reference_education_levels', function (Blueprint $table): void {
            $table->smallInteger('rank')->change();
        });

        Schema::table('reference_team_sizes', function (Blueprint $table): void {
            $table->integer('min_size')->nullable()->change();
            $table->integer('max_size')->nullable()->change();
        });
    }
};
