<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-interviewers.md` section 1: the permission `interviews.participate` (be on an interview's panel). Nobody loses access: whoever holds
 * `interviews.manage` (a member or a pending invitation) gets it. Members on the old Interviewer preset keep what they have and show as custom.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now()->format('Y-m-d H:i:s');
        $table = Schema::getConnection()->getTablePrefix().'company_member_permissions';

        DB::statement(<<<SQL
            INSERT INTO "{$table}" (company_member_id, permission, created_at)
            SELECT company_member_id, 'interviews.participate', '{$now}' FROM "{$table}" WHERE permission = 'interviews.manage'
        SQL);

        foreach (DB::table('company_invitations')->whereNotNull('permissions')->get(['id', 'permissions']) as $invitation) {
            $names = (array) json_decode((string) $invitation->permissions, true);

            if (in_array('interviews.manage', $names, true) && ! in_array('interviews.participate', $names, true)) {
                $names[] = 'interviews.participate';
                DB::table('company_invitations')->where('id', $invitation->id)->update(['permissions' => json_encode($names)]);
            }
        }
    }

    public function down(): void
    {
        DB::table('company_member_permissions')->where('permission', 'interviews.participate')->delete();

        foreach (DB::table('company_invitations')->whereNotNull('permissions')->get(['id', 'permissions']) as $invitation) {
            $names = array_values(array_diff((array) json_decode((string) $invitation->permissions, true), ['interviews.participate']));
            DB::table('company_invitations')->where('id', $invitation->id)->update(['permissions' => json_encode($names)]);
        }
    }
};
