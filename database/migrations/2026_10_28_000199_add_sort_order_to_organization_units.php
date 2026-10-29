<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/erp-organization.md` section 5: the order of the units of a legal entity, the order of the org chart and the table. It belongs to the unit, not to a dated version; existing units
 * start in the order of their codes, which is how they were listed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_units', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('status');
        });

        $position = [];

        foreach (DB::table('organization_units')->orderBy('legal_entity_id')->orderBy('code')->get(['id', 'legal_entity_id']) as $unit) {
            $position[$unit->legal_entity_id] = ($position[$unit->legal_entity_id] ?? 0) + 1;
            DB::table('organization_units')->where('id', $unit->id)->update(['sort_order' => $position[$unit->legal_entity_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('organization_units', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
