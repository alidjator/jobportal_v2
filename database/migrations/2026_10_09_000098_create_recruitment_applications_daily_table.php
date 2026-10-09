<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Applications submitted per Jakarta day. A count only, so any range can be added up; it points at no person.
        Schema::create('recruitment_applications_daily', function (Blueprint $table): void {
            $table->date('day');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('job_id');
            // `unknown` for an application from before the source was recorded.
            $table->string('source', 15);
            // The band of the match score: unknown, low, fair, good or strong.
            $table->string('match_band', 10);
            $table->unsignedInteger('applications');

            $table->primary(['day', 'job_id', 'source', 'match_band'], 'recruitment_applications_daily_primary');
            $table->index(['company_id', 'day'], 'recruitment_applications_daily_company_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_applications_daily');
    }
};
