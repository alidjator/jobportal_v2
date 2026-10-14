<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_members', function (Blueprint $table): void {
            $table->boolean('is_owner')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamp('suspended_at')->nullable();
            // No foreign key: accounts belong to Identity (cross-context references are added in migration 000102's way, and an erased account must not block).
            $table->unsignedBigInteger('suspended_by_user_id')->nullable();
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $connection->statement("ALTER TABLE \"{$prefix}company_members\" ADD CONSTRAINT \"{$prefix}company_members_status_check\" CHECK (\"status\" IN ('active', 'suspended'))");
        // One owner per company, and only a company admin can be it.
        $connection->statement("CREATE UNIQUE INDEX \"{$prefix}company_members_one_owner\" ON \"{$prefix}company_members\" (\"company_id\") WHERE \"is_owner\"");
        $connection->statement("ALTER TABLE \"{$prefix}company_members\" ADD CONSTRAINT \"{$prefix}company_members_owner_is_admin_check\" CHECK (NOT \"is_owner\" OR \"role\" = 'company_admin')");

        Schema::table('company_companies', function (Blueprint $table): void {
            $table->jsonb('invitation_email_domains')->nullable();
        });

        // The oldest company admin of each company becomes its owner.
        foreach (DB::table('company_members')->where('role', 'company_admin')->orderBy('created_at')->orderBy('id')->get(['id', 'company_id']) as $row) {
            if (! DB::table('company_members')->where('company_id', $row->company_id)->where('is_owner', true)->exists()) {
                DB::table('company_members')->where('id', $row->id)->update(['is_owner' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('company_companies', function (Blueprint $table): void {
            $table->dropColumn('invitation_email_domains');
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $connection->statement("DROP INDEX \"{$prefix}company_members_one_owner\"");
        $connection->statement("ALTER TABLE \"{$prefix}company_members\" DROP CONSTRAINT \"{$prefix}company_members_owner_is_admin_check\"");
        $connection->statement("ALTER TABLE \"{$prefix}company_members\" DROP CONSTRAINT \"{$prefix}company_members_status_check\"");

        Schema::table('company_members', function (Blueprint $table): void {
            $table->dropColumn(['is_owner', 'status', 'suspended_at', 'suspended_by_user_id']);
        });
    }
};
