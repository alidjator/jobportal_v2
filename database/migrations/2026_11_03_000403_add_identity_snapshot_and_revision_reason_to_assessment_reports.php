<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-institutions.md` sections 3 and 4: a report keeps a copy of the institution's identity (name, address, practice licence, contact and the logo copied into the report's folder)
 * as it was when the report was signed, issued automatically or revised, so a later change of the institution never alters an issued report; and a revision of a signed report keeps the reason the
 * psychologist gave (never shown to the company or the candidate). Both live on the report and on its revision rows. `signer_signature_path` (migration 000195) holds the copy of the signature image.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['assessment_reports', 'assessment_report_revisions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->jsonb('provider_identity')->nullable();
                $table->text('revision_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['assessment_reports', 'assessment_report_revisions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['provider_identity', 'revision_reason']);
            });
        }
    }
};
