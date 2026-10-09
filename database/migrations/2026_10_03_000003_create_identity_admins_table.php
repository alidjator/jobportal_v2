<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('avatar_path')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'identity_admins';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_status_check\" CHECK (\"status\" IN ('active', 'suspended', 'deactivated'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_admins');
    }
};
