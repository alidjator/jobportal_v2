<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string STATUSES = "'sent', 'opened', 'claimed', 'declined', 'expired', 'cancelled'";

    public function up(): void
    {
        Schema::create('jobinvitation_email_invitations', function (Blueprint $table): void {
            $table->id();
            // The company, the job and the members live in other contexts: plain columns, no foreign keys.
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('job_id');
            // The name and the email are removed when the invitation is purged (`docs/design/phase-8-manual-candidates.md` section 4).
            $table->string('name', 150)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('channel', 20);
            $table->string('note', 1000)->nullable();
            $table->string('token_hash', 64);
            $table->string('cv_path')->nullable();
            $table->string('cv_name', 100)->nullable();
            $table->string('status', 10)->default('sent');
            $table->unsignedBigInteger('invited_by_user_id');
            $table->unsignedBigInteger('claimed_by_user_id')->nullable();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('resent_count')->default(0);
            $table->timestamp('last_sent_at');
            $table->timestamps();

            $table->unique('token_hash', 'jobinvitation_email_invitations_token_unique');
            $table->index(['job_id', 'created_at'], 'jobinvitation_email_invitations_job_index');
            $table->index(['company_id', 'created_at'], 'jobinvitation_email_invitations_company_index');
            $table->index(['status', 'created_at'], 'jobinvitation_email_invitations_status_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'jobinvitation_email_invitations';
        $applications = $connection->getTablePrefix().'recruitment_applications';

        $connection->statement('ALTER TABLE "'.$tableName.'" ADD CONSTRAINT "'.$tableName.'_status_check" CHECK ("status" IN ('.self::STATUSES.'))');
        $connection->statement('ALTER TABLE "'.$tableName.'" ADD CONSTRAINT "'.$tableName.'_channel_check" CHECK ("channel" IN (\'email_cv\', \'walk_in\', \'referral\', \'job_fair\', \'other\'))');
        // One open invitation per job and email address.
        $connection->statement('CREATE UNIQUE INDEX "jobinvitation_email_invitations_open_unique" ON "'.$tableName.'" ("job_id", lower("email")) WHERE "status" IN (\'sent\', \'opened\')');

        $connection->statement('ALTER TABLE "'.$applications.'" DROP CONSTRAINT "'.$applications.'_source_check"');
        $connection->statement('ALTER TABLE "'.$applications.'" ADD CONSTRAINT "'.$applications.'_source_check" CHECK ("source" IS NULL OR "source" IN (\'search\', \'recommendation\', \'alert\', \'direct\', \'invitation\', \'shared\', \'manual\'))');
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $applications = $connection->getTablePrefix().'recruitment_applications';

        $connection->statement('UPDATE "'.$applications.'" SET source = \'direct\' WHERE source = \'manual\'');
        $connection->statement('ALTER TABLE "'.$applications.'" DROP CONSTRAINT "'.$applications.'_source_check"');
        $connection->statement('ALTER TABLE "'.$applications.'" ADD CONSTRAINT "'.$applications.'_source_check" CHECK ("source" IS NULL OR "source" IN (\'search\', \'recommendation\', \'alert\', \'direct\', \'invitation\', \'shared\'))');

        Schema::dropIfExists('jobinvitation_email_invitations');
    }
};
