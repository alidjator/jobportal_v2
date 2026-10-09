<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_terms_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('identity_users')->cascadeOnDelete();
            $table->string('document_type', 20);
            $table->unsignedInteger('document_version');
            $table->char('locale', 2);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();

            // The generated name would exceed the identifier limit of PostgreSQL (63 characters).
            $table->unique(['user_id', 'document_type', 'document_version'], 'terms_acceptances_user_document_version_unique');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'identity_terms_acceptances';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_document_type_check\" CHECK (\"document_type\" IN ('terms', 'privacy'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_terms_acceptances');
    }
};
