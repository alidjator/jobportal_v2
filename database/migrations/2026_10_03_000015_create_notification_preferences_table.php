<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('identity_users')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('channel', 10);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['user_id', 'key', 'channel']);
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'notification_preferences';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_channel_check\" CHECK (\"channel\" IN ('email', 'push'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
