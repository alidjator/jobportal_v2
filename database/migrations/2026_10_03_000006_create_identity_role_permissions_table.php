<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_role_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('identity_permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('identity_roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_role_permissions');
    }
};
