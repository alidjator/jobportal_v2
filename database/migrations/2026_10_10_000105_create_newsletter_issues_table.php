<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One issue of the newsletter: a Markdown body written by an admin, sent once to every confirmed subscriber.
        Schema::create('newsletter_issues', function (Blueprint $table): void {
            $table->id();
            $table->string('subject', 150);
            $table->text('body');
            $table->string('status', 10)->default('draft');
            $table->foreignId('created_by_admin_id')->constrained('identity_admins')->restrictOnDelete();
            $table->foreignId('sent_by_admin_id')->nullable()->constrained('identity_admins')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            // Set when every delivery of the issue has been queued; the issue is finished only after that and once no delivery is still queued.
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamps();

            $table->index(['status', 'id'], 'newsletter_issues_status_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'newsletter_issues';

        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_status_check\" CHECK (\"status\" IN ('draft', 'sending', 'sent'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_issues');
    }
};
