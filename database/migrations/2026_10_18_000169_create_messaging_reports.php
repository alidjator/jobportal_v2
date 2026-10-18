<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-platform-admin.md` section 5: chat reports by either side, and the job seeker's block of a conversation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_conversations', function (Blueprint $table): void {
            $table->dateTime('blocked_by_seeker_at')->nullable();
        });

        Schema::create('messaging_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('messaging_conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messaging_messages')->nullOnDelete();
            $table->string('reporter_side', 10);
            // The reporter lives in Identity: a plain column, cleared when the account is erased (it is not a foreign key).
            $table->unsignedBigInteger('reporter_user_id')->nullable();
            $table->string('reason', 20);
            $table->string('note', 1000)->nullable();
            // The reported message and up to 10 earlier ones, kept as they were; cleared 180 days after the decision and by an account erasure.
            $table->json('snapshot')->nullable();
            $table->string('status', 10)->default('open');
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('identity_admins')->restrictOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'messaging_reports_status_index');
            $table->index(['conversation_id', 'reporter_user_id', 'status'], 'messaging_reports_open_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'messaging_reports';

        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_side_check\" CHECK (\"reporter_side\" IN ('job_seeker', 'company'))");
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_reason_check\" CHECK (\"reason\" IN ('scam_or_fee', 'harassment', 'spam', 'inappropriate', 'other'))");
        $connection->statement("ALTER TABLE \"{$tableName}\" ADD CONSTRAINT \"{$tableName}_status_check\" CHECK (\"status\" IN ('open', 'dismissed', 'actioned'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_reports');

        Schema::table('messaging_conversations', function (Blueprint $table): void {
            $table->dropColumn('blocked_by_seeker_at');
        });
    }
};
