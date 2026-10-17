<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-interview-calendar.md` section 1: the calendar reads the start dates of accepted offers inside a window. (Interviews, offer deadlines and test due times are indexed already.) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_offers', function (Blueprint $table): void {
            $table->index(['status', 'start_date'], 'recruitment_offers_status_start_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_offers', function (Blueprint $table): void {
            $table->dropIndex('recruitment_offers_status_start_date_index');
        });
    }
};
