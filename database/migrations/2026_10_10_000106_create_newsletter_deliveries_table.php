<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per issue and subscriber. The unique key makes a repeated dispatch send each person at most once.
        Schema::create('newsletter_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('newsletter_issues')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('newsletter_subscribers')->cascadeOnDelete();
            $table->string('status', 10)->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['issue_id', 'subscriber_id'], 'newsletter_deliveries_unique');
            $table->index(['issue_id', 'status'], 'newsletter_deliveries_status_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'newsletter_deliveries';

        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_status_check\" CHECK (\"status\" IN ('queued', 'sent', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_deliveries');
    }
};
