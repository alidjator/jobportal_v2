<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The Recruiter preset: exactly what a recruiter could do before permissions existed, so existing recruiters keep their access. */
    private const array RECRUITER_PERMISSIONS = [
        'jobs.create', 'jobs.publish', 'applications.view', 'applications.manage', 'applications.export', 'interviews.manage', 'offers.manage',
        'assessments.send', 'candidates.search', 'talent_pool.manage', 'messages.send', 'interview_kits.manage',
    ];

    public function up(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        foreach (['company_members', 'company_invitations'] as $table) {
            $connection->statement("ALTER TABLE \"{$prefix}{$table}\" DROP CONSTRAINT \"{$prefix}{$table}_role_check\"");
            DB::table($table)->where('role', 'recruiter')->update(['role' => 'member']);
            $connection->statement(
                "ALTER TABLE \"{$prefix}{$table}\" ADD CONSTRAINT \"{$prefix}{$table}_role_check\" CHECK (\"role\" IN ('company_admin', 'member'))"
            );
        }

        Schema::create('company_member_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_member_id')->constrained('company_members')->cascadeOnDelete();
            $table->string('permission', 50);
            $table->timestamp('created_at')->nullable();

            $table->unique(['company_member_id', 'permission']);
        });

        Schema::table('company_invitations', function (Blueprint $table): void {
            $table->string('name', 100)->nullable();
            $table->jsonb('permissions')->nullable();
        });

        $now = now()->format('Y-m-d H:i:s');

        foreach (DB::table('company_members')->where('role', 'member')->pluck('id') as $memberId) {
            DB::table('company_member_permissions')->insert(array_map(
                static fn (string $permission): array => ['company_member_id' => $memberId, 'permission' => $permission, 'created_at' => $now],
                self::RECRUITER_PERMISSIONS,
            ));
        }

        DB::table('company_invitations')->where('role', 'member')->update(['permissions' => json_encode(self::RECRUITER_PERMISSIONS)]);
    }

    public function down(): void
    {
        Schema::table('company_invitations', function (Blueprint $table): void {
            $table->dropColumn(['name', 'permissions']);
        });

        Schema::dropIfExists('company_member_permissions');

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        foreach (['company_members', 'company_invitations'] as $table) {
            $connection->statement("ALTER TABLE \"{$prefix}{$table}\" DROP CONSTRAINT \"{$prefix}{$table}_role_check\"");
            DB::table($table)->where('role', 'member')->update(['role' => 'recruiter']);
            $connection->statement(
                "ALTER TABLE \"{$prefix}{$table}\" ADD CONSTRAINT \"{$prefix}{$table}_role_check\" CHECK (\"role\" IN ('company_admin', 'recruiter'))"
            );
        }
    }
};
