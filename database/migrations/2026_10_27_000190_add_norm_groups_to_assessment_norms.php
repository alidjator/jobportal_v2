<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-tools.md` section 1: a norm row may hold only for an age range and an education rank range (a row without them applies to
 * everyone), and an attempt keeps the age and the education rank of its candidate at the time of applying, so a later erasure of the snapshot does not change a report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_norms', function (Blueprint $table): void {
            $table->unsignedSmallInteger('age_min')->nullable();
            $table->unsignedSmallInteger('age_max')->nullable();
            $table->unsignedSmallInteger('education_rank_min')->nullable();
            $table->unsignedSmallInteger('education_rank_max')->nullable();
        });

        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('norm_age')->nullable();
            $table->unsignedSmallInteger('norm_education_rank')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->dropColumn(['norm_age', 'norm_education_rank']);
        });

        Schema::table('assessment_norms', function (Blueprint $table): void {
            $table->dropColumn(['age_min', 'age_max', 'education_rank_min', 'education_rank_max']);
        });
    }
};
