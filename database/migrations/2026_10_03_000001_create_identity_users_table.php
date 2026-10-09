<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_users', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('avatar_path')->nullable();
            $table->char('locale', 2)->default('id');
            $table->string('registration_campaign_code', 64)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'identity_users';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_type_check\" CHECK (\"type\" IN ('job_seeker', 'company_member'))"
        );
        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_status_check\" CHECK (\"status\" IN ('active', 'suspended', 'deactivated'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_users');
    }
};
