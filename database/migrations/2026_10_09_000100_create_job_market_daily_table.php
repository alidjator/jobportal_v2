<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jobs that went live and applications received per Jakarta day, by category, province and experience level (0 when the job has none), with the salary of the
        // jobs that show one per salary period. Counts and sums only, so any range can be added up.
        Schema::create('job_market_daily', function (Blueprint $table): void {
            $table->date('day');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('experience_level_id');
            // 0 for a job without a visible salary.
            $table->unsignedBigInteger('salary_period_id');
            $table->unsignedInteger('jobs');
            $table->unsignedInteger('applications');
            // Over the jobs with a visible salary: the sums of the lower and upper bound, and the lowest and the highest bound.
            $table->unsignedBigInteger('salary_min_sum');
            $table->unsignedBigInteger('salary_max_sum');
            $table->unsignedBigInteger('salary_lowest')->nullable();
            $table->unsignedBigInteger('salary_highest')->nullable();

            $table->primary(['day', 'company_id', 'category_id', 'province_id', 'experience_level_id', 'salary_period_id'], 'job_market_daily_primary');
            $table->index(['company_id', 'day'], 'job_market_daily_company_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_market_daily');
    }
};
