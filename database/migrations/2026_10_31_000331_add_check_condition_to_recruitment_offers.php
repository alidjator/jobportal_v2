<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_offers', function (Blueprint $table): void {
            $table->boolean('conditional_on_checks')->default(false);
            // The ids of the check types the offer depends on (`docs/design/phase-8-pre-employment-checks.md` section 4); empty unless `conditional_on_checks`.
            $table->json('condition_check_type_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_offers', function (Blueprint $table): void {
            $table->dropColumn(['conditional_on_checks', 'condition_check_type_ids']);
        });
    }
};
