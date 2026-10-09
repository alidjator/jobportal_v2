<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->unsignedInteger('version');
            $table->jsonb('title');
            $table->jsonb('body');
            $table->string('status', 20);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['type', 'version']);
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'content_legal_documents';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_type_check\" CHECK (\"type\" IN ('terms', 'privacy'))"
        );
        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_status_check\" CHECK (\"status\" IN ('draft', 'published', 'archived'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('content_legal_documents');
    }
};
