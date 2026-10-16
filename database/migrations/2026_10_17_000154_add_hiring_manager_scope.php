<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-hiring-managers.md` sections 1 and 2: the role of an assignee on a job, the stage boundary of a job, the marker that shows a bound
 * hiring manager an application, and the review permission for everyone who could already manage applicants.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        Schema::table('job_assignments', function (Blueprint $table): void {
            $table->string('role', 20)->default('recruiter');
        });
        $connection->statement("ALTER TABLE \"{$prefix}job_assignments\" ADD CONSTRAINT \"{$prefix}job_assignments_role_check\" CHECK (\"role\" IN ('recruiter', 'hiring_manager'))");

        // A stage of Recruitment: a plain column, checked by the use case (the stage may be deleted later, which clears it).
        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->unsignedBigInteger('hiring_manager_from_stage_id')->nullable();
        });

        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->timestamp('hiring_manager_visible_at')->nullable();
        });

        $this->fillMarkers($prefix);
        $this->grantReview($prefix);
    }

    public function down(): void
    {
        DB::table('company_member_permissions')->where('permission', 'applications.review')->delete();

        foreach (DB::table('company_invitations')->whereNotNull('permissions')->get(['id', 'permissions']) as $invitation) {
            $names = array_values(array_diff((array) json_decode((string) $invitation->permissions, true), ['applications.review']));
            DB::table('company_invitations')->where('id', $invitation->id)->update(['permissions' => json_encode($names)]);
        }

        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->dropColumn('hiring_manager_visible_at');
        });
        Schema::table('job_jobs', function (Blueprint $table): void {
            $table->dropColumn('hiring_manager_from_stage_id');
        });
        Schema::table('job_assignments', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    /**
     * The default boundary (the first interview stage of the company): an application is visible from the first time it entered a stage at or after it,
     * or from the time it entered its current stage when no history says so.
     */
    private function fillMarkers(string $prefix): void
    {
        $applications = $prefix.'recruitment_applications';
        $history = $prefix.'recruitment_application_stage_history';
        $stages = $prefix.'recruitment_stages';

        DB::statement(<<<SQL
            UPDATE "{$applications}" AS a
            SET hiring_manager_visible_at = COALESCE(
                (SELECT MIN(h.created_at) FROM "{$history}" AS h JOIN "{$stages}" AS hs ON hs.id = h.to_stage_id
                 WHERE h.application_id = a.id AND hs.type NOT IN ('rejected', 'withdrawn') AND hs.position >= b.position),
                a.stage_entered_at
            )
            FROM (SELECT company_id, MIN(position) AS position FROM "{$stages}" WHERE type = 'interview' GROUP BY company_id) AS b
            WHERE b.company_id = a.company_id
              AND (
                EXISTS (SELECT 1 FROM "{$history}" AS h JOIN "{$stages}" AS hs ON hs.id = h.to_stage_id
                        WHERE h.application_id = a.id AND hs.type NOT IN ('rejected', 'withdrawn') AND hs.position >= b.position)
                OR (a.stage_type NOT IN ('rejected', 'withdrawn') AND (SELECT cs.position FROM "{$stages}" AS cs WHERE cs.id = a.stage_id) >= b.position)
              )
        SQL);
    }

    /** Nobody loses access: whoever holds `applications.manage` (a member or a pending invitation) gets `applications.review`. */
    private function grantReview(string $prefix): void
    {
        $now = now()->format('Y-m-d H:i:s');
        $table = $prefix.'company_member_permissions';

        DB::statement(<<<SQL
            INSERT INTO "{$table}" (company_member_id, permission, created_at)
            SELECT company_member_id, 'applications.review', '{$now}' FROM "{$table}" WHERE permission = 'applications.manage'
        SQL);

        foreach (DB::table('company_invitations')->whereNotNull('permissions')->get(['id', 'permissions']) as $invitation) {
            $names = (array) json_decode((string) $invitation->permissions, true);

            if (in_array('applications.manage', $names, true) && ! in_array('applications.review', $names, true)) {
                $names[] = 'applications.review';
                DB::table('company_invitations')->where('id', $invitation->id)->update(['permissions' => json_encode($names)]);
            }
        }
    }
};
