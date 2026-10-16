<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-governance.md` section 3: the group an audit entry belongs to, for the group activity log. */
return new class extends Migration
{
    public function up(): void
    {
        // No foreign key across contexts: the log outlives what it describes.
        Schema::table('platform_audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('group_id')->nullable();

            $table->index(['group_id', 'occurred_at'], 'platform_audit_logs_group_occurred_index');
        });

        $prefix = DB::getTablePrefix();
        $audit = $prefix.'platform_audit_logs';
        $companies = $prefix.'company_companies';
        $entities = $prefix.'organization_legal_entities';

        // Entries about a group, then the entries that belong to a company of an internal tenant (the company activity log decided which company).
        DB::statement("UPDATE \"{$audit}\" SET group_id = subject_id WHERE subject_type = 'organization_group' AND subject_id IS NOT NULL AND group_id IS NULL");
        DB::statement(
            "UPDATE \"{$audit}\" AS a SET group_id = e.group_id FROM \"{$companies}\" AS c JOIN \"{$entities}\" AS e ON e.id = c.legal_entity_id WHERE a.group_id IS NULL AND a.company_id = c.id"
        );
    }

    public function down(): void
    {
        Schema::table('platform_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('platform_audit_logs_group_occurred_index');
            $table->dropColumn('group_id');
        });
    }
};
