<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-imported-history-in-reports.md` section 2: `migrated_at` marks a job that came from the karir.pkp import, so the time-to-fill report can leave it out (its
 * publication and hires happened before the platform). The rehearsal databases are backfilled from the id map; a database without imported jobs has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->timestamp('migrated_at')->nullable();
        });

        DB::statement('CREATE INDEX "job_jobs_migrated_at_index" ON "job_jobs" ("migrated_at") WHERE "migrated_at" IS NOT NULL');

        if (Schema::hasTable('legacyimport_id_map')) {
            DB::statement(
                'UPDATE "job_jobs" SET "migrated_at" = "m"."imported_at" FROM "legacyimport_id_map" AS "m" '
                .'WHERE "m"."entity" = \'job\' AND "m"."new_id" = "job_jobs"."id"',
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS "job_jobs_migrated_at_index"');

        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->dropColumn('migrated_at');
        });
    }
};
