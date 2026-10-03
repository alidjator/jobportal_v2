<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('identity_users')->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 10);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'notification_device_tokens';

        $connection->statement(
            "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$tableName}_platform_check` CHECK (`platform` IN ('android', 'ios', 'web'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_device_tokens');
    }
};
