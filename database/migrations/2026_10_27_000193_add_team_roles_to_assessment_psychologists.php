<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-institutions.md` section 1: leads manage their institution's team. A psychologist gets a `role`; an invitation gets the role
 * it grants and may come from a lead instead of an admin. An invitation by a lead carries no licence, so the licence columns of both tables become nullable:
 * the psychologist submits the licence after accepting and cannot sign until then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_psychologists', function (Blueprint $table): void {
            $table->string('role', 20)->default('member')->after('provider_id');
            $table->string('str_number', 60)->nullable()->change();
            $table->date('str_valid_until')->nullable()->change();
            $table->string('silp_number', 60)->nullable()->change();
            $table->date('silp_valid_until')->nullable()->change();
        });

        Schema::table('assessment_psychologist_invitations', function (Blueprint $table): void {
            $table->string('role', 20)->default('member')->after('name');
            $table->string('str_number', 60)->nullable()->change();
            $table->date('str_valid_until')->nullable()->change();
            $table->string('silp_number', 60)->nullable()->change();
            $table->date('silp_valid_until')->nullable()->change();
            $table->unsignedBigInteger('invited_by_admin_id')->nullable()->change();
            $table->foreignId('invited_by_psychologist_id')->nullable()->after('invited_by_admin_id')
                ->constrained('assessment_psychologists')->restrictOnDelete();
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $psychologists = $prefix.'assessment_psychologists';
        $invitations = $prefix.'assessment_psychologist_invitations';

        $connection->statement("ALTER TABLE \"{$psychologists}\" ADD CONSTRAINT \"{$psychologists}_role_check\" CHECK (\"role\" IN ('member', 'lead'))");
        $connection->statement("ALTER TABLE \"{$invitations}\" ADD CONSTRAINT \"{$invitations}_role_check\" CHECK (\"role\" IN ('member', 'lead'))");
        $connection->statement(
            "ALTER TABLE \"{$invitations}\" ADD CONSTRAINT \"{$invitations}_inviter_check\" CHECK ((\"invited_by_admin_id\" IS NULL) <> (\"invited_by_psychologist_id\" IS NULL))",
        );
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $psychologists = $prefix.'assessment_psychologists';
        $invitations = $prefix.'assessment_psychologist_invitations';

        $connection->statement("ALTER TABLE \"{$invitations}\" DROP CONSTRAINT \"{$invitations}_inviter_check\"");
        $connection->statement("ALTER TABLE \"{$invitations}\" DROP CONSTRAINT \"{$invitations}_role_check\"");
        $connection->statement("ALTER TABLE \"{$psychologists}\" DROP CONSTRAINT \"{$psychologists}_role_check\"");

        Schema::table('assessment_psychologist_invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invited_by_psychologist_id');
            $table->dropColumn('role');
        });

        Schema::table('assessment_psychologists', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
