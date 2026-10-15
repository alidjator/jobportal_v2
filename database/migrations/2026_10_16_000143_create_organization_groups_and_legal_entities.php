<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-organization.md` sections 2, 3 and 7: groups, legal entities, the tenant link and the group roles. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('status', 10)->default('active');
            $table->string('status_reason', 500)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_legal_entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('organization_groups')->restrictOnDelete();
            $table->string('legal_name', 200);
            $table->string('legal_form', 15);
            $table->string('npwp', 25)->nullable();
            $table->string('nib', 20)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->foreignId('province_id')->nullable()->constrained('reference_provinces')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('reference_cities')->restrictOnDelete();
            $table->foreignId('subdistrict_id')->nullable()->constrained('reference_subdistricts')->restrictOnDelete();
            $table->foreignId('village_id')->nullable()->constrained('reference_villages')->restrictOnDelete();
            $table->foreignId('postal_code_id')->nullable()->constrained('reference_postal_codes')->restrictOnDelete();
            $table->string('status', 10)->default('active');
            $table->timestamps();

            $table->unique(['group_id', 'legal_name'], 'organization_legal_entities_group_name_unique');
        });

        // The internal tenant link: set = internal tenant (one legal entity, one tenant), null = external tenant. `suspended_by_group_at` tells a restore of the group
        // which tenants the group suspension suspended.
        Schema::table('company_companies', function (Blueprint $table): void {
            $table->foreignId('legal_entity_id')->nullable()->unique()->constrained('organization_legal_entities')->restrictOnDelete();
            $table->timestamp('suspended_by_group_at')->nullable();
        });

        Schema::create('organization_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('organization_groups')->restrictOnDelete();
            $table->foreignId('user_id')->unique()->constrained('identity_users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('organization_group_member_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_member_id')->constrained('organization_group_members')->cascadeOnDelete();
            $table->string('permission', 40);
            $table->timestamp('created_at')->nullable();

            $table->unique(['group_member_id', 'permission'], 'organization_group_member_permissions_unique');
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();
        $groups = $prefix.'organization_groups';
        $entities = $prefix.'organization_legal_entities';

        $connection->statement("ALTER TABLE \"{$groups}\" ADD CONSTRAINT \"{$groups}_status_check\" CHECK (\"status\" IN ('active', 'suspended'))");
        $connection->statement("ALTER TABLE \"{$entities}\" ADD CONSTRAINT \"{$entities}_status_check\" CHECK (\"status\" IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE \"{$entities}\" ADD CONSTRAINT \"{$entities}_legal_form_check\" CHECK (\"legal_form\" IN ('pt', 'pt_tbk', 'cv', 'firma', 'koperasi', 'yayasan', 'perum', 'persero', 'other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_group_member_permissions');
        Schema::dropIfExists('organization_group_members');

        Schema::table('company_companies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('legal_entity_id');
            $table->dropColumn('suspended_by_group_at');
        });

        Schema::dropIfExists('organization_legal_entities');
        Schema::dropIfExists('organization_groups');
    }
};
