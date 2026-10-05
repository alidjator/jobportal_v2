<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('role', 20);
            $table->timestamps();

            $table->index(['company_id', 'role']);
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'company_members';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_role_check` CHECK (`role` IN ('company_admin', 'recruiter'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('company_members');
    }
};
