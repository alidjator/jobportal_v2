<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Column => referenced table. Reference data is never deleted (only deactivated), so RESTRICT never gets in the way. */
    private const array REFERENCES = [
        'industry_id' => 'reference_industries',
        'organization_type_id' => 'reference_organization_types',
        'team_size_id' => 'reference_team_sizes',
        'province_id' => 'reference_provinces',
        'city_id' => 'reference_cities',
        'subdistrict_id' => 'reference_subdistricts',
        'village_id' => 'reference_villages',
        'postal_code_id' => 'reference_postal_codes',
    ];

    public function up(): void
    {
        Schema::table('company_companies', function (Blueprint $table): void {
            foreach (self::REFERENCES as $column => $referenced) {
                $table->foreign($column)->references('id')->on($referenced)->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_companies', function (Blueprint $table): void {
            foreach (array_keys(self::REFERENCES) as $column) {
                $table->dropForeign([$column]);
            }
        });
    }
};
