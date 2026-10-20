<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-activation.md` sections 4 and 11: marks the accounts that came from the karir.pkp import, so that the other contexts can hold
 * their scheduled emails until the first sign-in (through the Shared port `AccountActivation`) while the id map stays removable. The rehearsal
 * databases are backfilled from the id map; a database that never ran the import has no rows there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_users', function (Blueprint $table): void {
            $table->timestamp('imported_at')->nullable()->after('first_login_at');
        });

        DB::statement('CREATE INDEX "identity_users_imported_at_index" ON "identity_users" ("imported_at") WHERE "imported_at" IS NOT NULL');

        if (Schema::hasTable('legacyimport_id_map')) {
            DB::statement(
                'UPDATE "identity_users" SET "imported_at" = "map"."imported_at" FROM "legacyimport_id_map" AS "map" '
                ."WHERE \"map\".\"entity\" = 'user' AND \"map\".\"new_id\" = \"identity_users\".\"id\"",
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS "identity_users_imported_at_index"');

        Schema::table('identity_users', function (Blueprint $table): void {
            $table->dropColumn('imported_at');
        });
    }
};
