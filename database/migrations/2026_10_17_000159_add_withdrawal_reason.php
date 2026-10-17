<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-withdrawals.md` section 1: the fixed reason of a withdrawal, on the history entry into `withdrawn` and on the application itself
 * (for the lists and the reports). Both are nullable: a withdrawal by an old client, an imported one and one by the applicant may carry no reason.
 */
return new class extends Migration
{
    private const string VALUES = "'accepted_other_offer', 'salary', 'location', 'role_mismatch', 'process_too_long', 'personal', 'unreachable', 'other'";

    public function up(): void
    {
        foreach (['recruitment_application_stage_history', 'recruitment_applications'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('withdrawal_reason', 40)->nullable();
            });

            DB::statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$table}_withdrawal_reason_check\" CHECK (\"withdrawal_reason\" IS NULL OR \"withdrawal_reason\" IN (".self::VALUES.'))');
        }
    }

    public function down(): void
    {
        foreach (['recruitment_application_stage_history', 'recruitment_applications'] as $table) {
            DB::statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$table}_withdrawal_reason_check\"");

            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('withdrawal_reason');
            });
        }
    }
};
