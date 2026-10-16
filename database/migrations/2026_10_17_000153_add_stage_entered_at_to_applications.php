<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-recruiter-workday.md` section 2: when an application entered its current stage, for the days in stage and the stale mark. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->timestamp('stage_entered_at')->useCurrent();
        });

        // The latest move into a stage, or the day it was submitted for an application that never moved.
        DB::statement('UPDATE '.DB::getTablePrefix().'recruitment_applications AS a SET stage_entered_at = COALESCE('
            .'(SELECT MAX(h.created_at) FROM '.DB::getTablePrefix().'recruitment_application_stage_history AS h WHERE h.application_id = a.id), a.applied_at)');
    }

    public function down(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->dropColumn('stage_entered_at');
        });
    }
};
