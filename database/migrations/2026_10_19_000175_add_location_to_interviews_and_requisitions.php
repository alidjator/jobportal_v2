<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/company-locations.md` section 4: an interview and a requisition may point to a work location of the tenant. A location that is referenced can only be deactivated. */
return new class extends Migration
{
    private const array TABLES = ['recruitment_interviews', 'workforce_requisitions'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('location_id')->nullable()->constrained('company_locations')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('location_id');
            });
        }
    }
};
