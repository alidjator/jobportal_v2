<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-talent-pool.md` section 1: where an entry came from, the consenting application it was saved from and the member who saved it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talentpool_entries', function (Blueprint $table): void {
            // `search` (a findable candidate) or `application` (saved from an application); the entries that exist today came from the search.
            $table->string('source', 20)->default('search');
            // The application lives in another context: a plain column, no foreign key.
            $table->unsignedBigInteger('application_id')->nullable();
            $table->foreignId('saved_by_user_id')->nullable()->constrained('identity_users')->restrictOnDelete();
        });

        $connection = Schema::getConnection();
        $table = $connection->getTablePrefix().'talentpool_entries';

        $connection->statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$table}_source_check\" CHECK (\"source\" IN ('search', 'application'))");
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $table = $connection->getTablePrefix().'talentpool_entries';

        $connection->statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$table}_source_check\"");

        Schema::table('talentpool_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('saved_by_user_id');
            $table->dropColumn(['source', 'application_id']);
        });
    }
};
