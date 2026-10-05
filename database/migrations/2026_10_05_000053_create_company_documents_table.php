<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->foreignId('document_type_id')->constrained('company_document_types')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->string('rejection_reason', 30)->nullable();
            $table->string('rejection_note', 500)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['company_id', 'document_type_id']);
            $table->index('status');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'company_documents';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_status_check` CHECK (`status` IN ('pending', 'approved', 'rejected'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documents');
    }
};
