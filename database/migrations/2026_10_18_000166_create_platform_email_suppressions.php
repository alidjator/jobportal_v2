<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-platform-admin.md` section 6: the addresses non-essential email is no longer sent to. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_suppressions', function (Blueprint $table): void {
            $table->id();
            // Lower case, one row per address; a person's address is not a foreign key (the list outlives accounts).
            $table->string('email', 255)->unique();
            $table->string('reason', 20);
            $table->string('source', 50)->nullable();
            $table->string('details', 1000)->nullable();
            $table->timestamp('created_at');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'platform_email_suppressions';

        $connection->statement(
            "ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_reason_check\" CHECK (\"reason\" IN ('hard_bounce', 'complaint', 'manual'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_email_suppressions');
    }
};
