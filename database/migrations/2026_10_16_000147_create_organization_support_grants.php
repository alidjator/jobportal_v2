<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-governance.md` section 1.1: temporary, revocable, read-only support access a group admin grants to a named platform admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_support_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('organization_groups')->restrictOnDelete();
            $table->foreignId('admin_id')->constrained('identity_admins')->restrictOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('identity_users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('identity_users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['group_id', 'created_at'], 'organization_support_grants_group_index');
            $table->index(['admin_id', 'expires_at'], 'organization_support_grants_admin_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_support_grants');
    }
};
