<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The long-term count of a job per Jakarta day: raw rows of `job_views` are folded into it before they are deleted (90 days), so no viewer trace is left.
        Schema::create('job_view_daily', function (Blueprint $table): void {
            $table->unsignedBigInteger('job_id');
            $table->date('day');
            $table->unsignedInteger('views');

            $table->primary(['job_id', 'day']);
            $table->index('day', 'job_view_daily_day_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_view_daily');
    }
};
