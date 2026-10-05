<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('role', 20);
            $table->char('token_hash', 64)->unique();
            $table->unsignedBigInteger('invited_by_user_id')->nullable();
            $table->unsignedBigInteger('invited_by_admin_id')->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'company_invitations';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_role_check` CHECK (`role` IN ('company_admin', 'recruiter'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('company_invitations');
    }
};
