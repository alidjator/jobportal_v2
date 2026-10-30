<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-job-posting.md` section 5: the extra profile items a job asks for before an application is taken (null for none). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->jsonb('apply_requirements')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->dropColumn('apply_requirements');
        });
    }
};
