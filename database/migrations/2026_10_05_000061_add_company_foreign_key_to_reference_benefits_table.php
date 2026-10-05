<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A benefit proposed by a company points to that company. Companies are never deleted (they are suspended), so RESTRICT never gets in the way. */
    public function up(): void
    {
        Schema::table('reference_benefits', function (Blueprint $table): void {
            $table->foreign('proposed_by_company_id')->references('id')->on('company_companies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reference_benefits', function (Blueprint $table): void {
            $table->dropForeign(['proposed_by_company_id']);
        });
    }
};
