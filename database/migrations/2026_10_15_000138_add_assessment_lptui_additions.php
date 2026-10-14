<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        // A new device takes the attempt over: the hash of the token of the device that holds it.
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->string('session_hash', 64)->nullable();
        });

        $events = $prefix.'assessment_integrity_events';
        $connection->statement("ALTER TABLE \"{$events}\" DROP CONSTRAINT \"{$events}_type_check\"");
        $connection->statement("ALTER TABLE \"{$events}\" ADD CONSTRAINT \"{$events}_type_check\" CHECK (\"type\" IN ('tab_switch', 'fullscreen_exit', 'paste', 'ip_change', 'idle', 'device_switch'))");

        // An onsite invitation shows where the test is taken; one file may go with the invitation (kept on the private disk).
        Schema::table('assessment_invitations', function (Blueprint $table): void {
            $table->string('mode', 10)->default('online');
            $table->string('location', 255)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('attachment_mime', 50)->nullable();
            $table->unsignedInteger('attachment_size_bytes')->nullable();
        });

        $invitations = $prefix.'assessment_invitations';
        $connection->statement("ALTER TABLE \"{$invitations}\" ADD CONSTRAINT \"{$invitations}_mode_check\" CHECK (\"mode\" IN ('online', 'onsite'))");
        $connection->statement("ALTER TABLE \"{$invitations}\" ADD CONSTRAINT \"{$invitations}_onsite_location_check\" CHECK (\"mode\" = 'online' OR \"location\" IS NOT NULL)");
        // Automatic issue of a report: the platform admin allows it per provider, a psychologist turns it on per battery and names the one who answers for the
        // reports; the norms and the battery carry the texts that make the explanation for the candidate without a hand-written one.
        Schema::table('assessment_providers', function (Blueprint $table): void {
            $table->boolean('auto_issue_allowed')->default(false);
        });

        Schema::table('assessment_tests', function (Blueprint $table) use ($prefix): void {
            $table->boolean('auto_issue')->default(false);
            $table->unsignedBigInteger('responsible_psychologist_id')->nullable();
            $table->foreign('responsible_psychologist_id', $prefix.'assessment_tests_responsible_foreign')->references('id')->on('assessment_psychologists')->restrictOnDelete();
        });

        Schema::table('assessment_norms', function (Blueprint $table): void {
            $table->text('explanation')->nullable();
        });

        // The explanation template may be written before the report template, so the body can be empty until then.
        Schema::table('assessment_report_templates', function (Blueprint $table): void {
            $table->text('body')->nullable()->change();
            $table->text('explanation_template')->nullable();
        });

        Schema::table('assessment_reports', function (Blueprint $table) use ($prefix): void {
            $table->boolean('auto_issued')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_psychologist_id')->nullable();
            $table->unsignedSmallInteger('revision')->default(0);
            $table->foreign('reviewed_by_psychologist_id', $prefix.'assessment_reports_reviewed_by_foreign')->references('id')->on('assessment_psychologists')->restrictOnDelete();
        });

        // A revision replaces the text of an automatically issued report under the same number with `-R1`, `-R2`; the version it replaced is kept here.
        Schema::create('assessment_report_revisions', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedSmallInteger('revision');
            $table->string('report_number', 40);
            $table->text('body');
            $table->text('explanation')->nullable();
            $table->unsignedBigInteger('signed_by_psychologist_id')->nullable();
            $table->string('signer_name', 150)->nullable();
            $table->string('signer_str_number', 60)->nullable();
            $table->string('signer_silp_number', 60)->nullable();
            $table->timestamp('signed_at');
            $table->timestamp('superseded_at');
            $table->timestamps();

            $table->foreign('report_id', $prefix.'assessment_revisions_report_foreign')->references('id')->on('assessment_reports')->cascadeOnDelete();
            $table->foreign('signed_by_psychologist_id', $prefix.'assessment_revisions_signer_foreign')->references('id')->on('assessment_psychologists')->restrictOnDelete();
            $table->unique(['report_id', 'revision'], $prefix.'assessment_revisions_report_revision_unique');
        });
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        $events = $prefix.'assessment_integrity_events';
        $connection->statement("DELETE FROM \"{$events}\" WHERE \"type\" IN ('idle', 'device_switch')");
        $connection->statement("ALTER TABLE \"{$events}\" DROP CONSTRAINT \"{$events}_type_check\"");
        $connection->statement("ALTER TABLE \"{$events}\" ADD CONSTRAINT \"{$events}_type_check\" CHECK (\"type\" IN ('tab_switch', 'fullscreen_exit', 'paste', 'ip_change'))");

        Schema::dropIfExists('assessment_report_revisions');

        Schema::table('assessment_reports', function (Blueprint $table) use ($prefix): void {
            $table->dropForeign($prefix.'assessment_reports_reviewed_by_foreign');
            $table->dropColumn(['auto_issued', 'reviewed_at', 'reviewed_by_psychologist_id', 'revision']);
        });

        $connection->statement("DELETE FROM \"{$prefix}assessment_report_templates\" WHERE \"body\" IS NULL");

        Schema::table('assessment_report_templates', function (Blueprint $table): void {
            $table->dropColumn('explanation_template');
            $table->text('body')->nullable(false)->change();
        });

        Schema::table('assessment_norms', function (Blueprint $table): void {
            $table->dropColumn('explanation');
        });

        Schema::table('assessment_tests', function (Blueprint $table) use ($prefix): void {
            $table->dropForeign($prefix.'assessment_tests_responsible_foreign');
            $table->dropColumn(['auto_issue', 'responsible_psychologist_id']);
        });

        Schema::table('assessment_providers', function (Blueprint $table): void {
            $table->dropColumn('auto_issue_allowed');
        });

        $invitations = $prefix.'assessment_invitations';
        $connection->statement("ALTER TABLE \"{$invitations}\" DROP CONSTRAINT \"{$invitations}_onsite_location_check\"");
        $connection->statement("ALTER TABLE \"{$invitations}\" DROP CONSTRAINT \"{$invitations}_mode_check\"");

        Schema::table('assessment_invitations', function (Blueprint $table): void {
            $table->dropColumn(['mode', 'location', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size_bytes']);
        });

        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->dropColumn('session_hash');
        });
    }
};
