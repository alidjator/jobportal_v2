<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-company-admin.md` sections 1, 2 and 4: two-factor sign-in for company members and the company activity log. */
return new class extends Migration
{
    public function up(): void
    {
        // The company an entry belongs to, for the company activity log. No foreign key across contexts: the log outlives a removed member.
        Schema::table('platform_audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable();

            $table->index(['company_id', 'occurred_at'], 'platform_audit_logs_company_occurred_index');
        });

        $audit = DB::getTablePrefix().'platform_audit_logs';
        $members = DB::getTablePrefix().'company_members';

        // Best effort, from the current memberships: an entry of a member who was removed stays empty. Entries about a company carry its id as the subject.
        DB::statement("UPDATE \"{$audit}\" SET company_id = subject_id WHERE subject_type = 'company' AND subject_id IS NOT NULL AND company_id IS NULL");
        DB::statement(
            "UPDATE \"{$audit}\" AS a SET company_id = m.company_id FROM \"{$members}\" AS m WHERE a.company_id IS NULL AND a.actor_type = 'user' AND a.actor_id = m.user_id"
        );

        // Two-factor now binds every company admin: one without a confirmed factor must enrol at the next sign-in, so the sessions it holds end here.
        // (The email that says so is sent only when the requirement starts after the rollout.)
        $factors = DB::getTablePrefix().'identity_user_two_factor';
        $tokens = DB::getTablePrefix().'personal_access_tokens';

        DB::statement(
            "DELETE FROM \"{$tokens}\" WHERE tokenable_type = 'user' AND tokenable_id IN ("
            ."SELECT m.user_id FROM \"{$members}\" AS m WHERE m.role = 'company_admin' "
            ."AND NOT EXISTS (SELECT 1 FROM \"{$factors}\" AS f WHERE f.user_id = m.user_id AND f.confirmed_at IS NOT NULL))"
        );
    }

    public function down(): void
    {
        Schema::table('platform_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('platform_audit_logs_company_occurred_index');
            $table->dropColumn('company_id');
        });
    }
};
