<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-job-copy-in-application.md`: the content of the job as the candidate saw it when they applied (title, description, work mode, location, salary when shown,
 * skills, benefits, tags), kept with the application and never changed. Applications that exist are filled once by `recruitment:backfill-job-snapshots` (marked `as_of_migration`),
 * the final import by the importer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->jsonb('job_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->dropColumn('job_snapshot');
        });
    }
};
