<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-platform-admin.md` section 7: platform announcements shown as a banner. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('body', 500);
            $table->string('link_url', 500)->nullable();
            $table->string('audience', 20);
            $table->string('level', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('created_by_admin_id')->constrained('identity_admins')->restrictOnDelete();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'content_announcements';

        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_audience_check\" CHECK (\"audience\" IN ('all', 'job_seekers', 'company_members'))");
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_level_check\" CHECK (\"level\" IN ('info', 'important'))");
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_window_check\" CHECK (\"ends_at\" > \"starts_at\")");
    }

    public function down(): void
    {
        Schema::dropIfExists('content_announcements');
    }
};
