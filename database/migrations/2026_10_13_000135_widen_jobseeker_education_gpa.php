<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy grades are kept as they are, and some are on a scale of 100 (for example 85). The rule of the new API (0.00 to 4.00, `HistoryRules`) still applies to
 * what users enter; it is no longer enforced by the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobseeker_educations', function (Blueprint $table): void {
            $table->decimal('gpa', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('jobseeker_educations', function (Blueprint $table): void {
            $table->decimal('gpa', 3, 2)->nullable()->change();
        });
    }
};
