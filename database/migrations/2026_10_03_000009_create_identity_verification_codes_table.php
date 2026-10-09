<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 10);
            $table->unsignedBigInteger('subject_id');
            $table->string('purpose', 30);
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('created_at')->nullable();

            // The generated name would exceed the identifier limit of PostgreSQL (63 characters).
            $table->index(['subject_type', 'subject_id', 'purpose'], 'verification_codes_subject_purpose_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'identity_verification_codes';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_subject_type_check\" CHECK (\"subject_type\" IN ('user', 'admin'))"
        );
        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_purpose_check\" CHECK (\"purpose\" IN ('email_verification', 'password_reset'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verification_codes');
    }
};
