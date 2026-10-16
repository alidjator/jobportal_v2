<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** `docs/design/erp-governance.md` section 3: the settings gain the `group` scope next to `company`. */
return new class extends Migration
{
    public function up(): void
    {
        $table = DB::getTablePrefix().'platform_setting_overrides';

        DB::statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$table}_scope_type_check\"");
        DB::statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$table}_scope_type_check\" CHECK (\"scope_type\" IN ('company', 'group'))");
    }

    public function down(): void
    {
        $table = DB::getTablePrefix().'platform_setting_overrides';

        DB::statement("DELETE FROM \"{$table}\" WHERE scope_type = 'group'");
        DB::statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$table}_scope_type_check\"");
        DB::statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$table}_scope_type_check\" CHECK (\"scope_type\" IN ('company'))");
    }
};
