<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-tools.md` section 7: the PDF of a signed report is rendered once and kept on the private disk with its SHA-256, so the file a company
 * downloads (and the hash the public check answers with) stays the same; a revision keeps the file of the version it replaces in its revision row. The numbering of the
 * migrations jumped to 0004xx on purpose (the 0003xx range is the invitations, presets and checks of 2026-10-30 and 2026-10-31).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['assessment_reports', 'assessment_report_revisions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('pdf_path', 255)->nullable();
                $table->char('pdf_sha256', 64)->nullable();
            });
        }

        Schema::table('assessment_report_revisions', function (Blueprint $table): void {
            $table->index('report_number');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_report_revisions', function (Blueprint $table): void {
            $table->dropIndex(['report_number']);
        });

        foreach (['assessment_reports', 'assessment_report_revisions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['pdf_path', 'pdf_sha256']);
            });
        }
    }
};
