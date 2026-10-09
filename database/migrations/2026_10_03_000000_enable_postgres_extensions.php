<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `pg_trgm` serves `ILIKE '%text%'` through trigram indexes; `unaccent` makes a search ignore accents.
     * `unaccent` is only STABLE, so an index cannot use it directly: `immutable_unaccent` is the IMMUTABLE wrapper that
     * both the indexes and `TextSearch` use.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION immutable_unaccent(text) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
            AS $$ SELECT public.unaccent('public.unaccent', $1) $$
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS immutable_unaccent(text)');
    }
};
