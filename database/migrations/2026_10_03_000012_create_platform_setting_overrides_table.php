<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_setting_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 128);
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id');
            $table->json('value');
            $table->unsignedBigInteger('updated_by_admin_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['key', 'scope_type', 'scope_id']);
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'platform_setting_overrides';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_scope_type_check` CHECK (`scope_type` IN ('company'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_setting_overrides');
    }
};
