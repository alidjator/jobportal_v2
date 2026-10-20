<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-activation.md` sections 6 and 11: `migrated_at` marks an application that came from the karir.pkp import and is the queryable form of what the profile
 * snapshot already said (`migrated`, `migrated_at`). An imported application is untouched while it has not left the stage it was imported into (`stage_entered_at <= migrated_at`). The
 * rehearsal databases are backfilled from the snapshot; a database without imported applications has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->timestamp('migrated_at')->nullable()->after('stage_entered_at');
        });

        DB::statement('CREATE INDEX "recruitment_applications_migrated_at_index" ON "recruitment_applications" ("migrated_at") WHERE "migrated_at" IS NOT NULL');

        DB::statement(
            'UPDATE "recruitment_applications" SET "migrated_at" = (("profile_snapshot"->>\'migrated_at\')::timestamptz AT TIME ZONE \'UTC\') '
            .'WHERE "profile_snapshot"->>\'migrated\' = \'true\' AND "profile_snapshot"->>\'migrated_at\' IS NOT NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS "recruitment_applications_migrated_at_index"');

        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->dropColumn('migrated_at');
        });
    }
};
