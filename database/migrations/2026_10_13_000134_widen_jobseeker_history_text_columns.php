<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy data is migrated as it is, so the columns that held a text of the old limits take longer ones. The limits of the new rules (`HistoryRules`: 100, 2000, 1000)
 * still apply to what users enter through the API; they are no longer enforced by the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobseeker_experiences', function (Blueprint $table): void {
            $table->string('company_name', 255)->change();
            $table->text('description')->nullable()->change();
        });

        Schema::table('jobseeker_educations', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('jobseeker_experiences', function (Blueprint $table): void {
            $table->string('company_name', 100)->change();
            $table->string('description', 2000)->nullable()->change();
        });

        Schema::table('jobseeker_educations', function (Blueprint $table): void {
            $table->string('description', 1000)->nullable()->change();
        });
    }
};
