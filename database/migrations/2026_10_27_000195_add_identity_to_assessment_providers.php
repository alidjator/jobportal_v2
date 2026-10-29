<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-institutions.md` section 3: the identity of an institution (address, practice licence, contact, logo) and the signature image of a psychologist, and the copy of
 * the signature that an issued report keeps, so a later change never alters a report that was issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_providers', function (Blueprint $table): void {
            $table->string('address', 500)->nullable();
            $table->string('practice_licence_number', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('logo_path', 255)->nullable();
        });

        Schema::table('assessment_psychologists', function (Blueprint $table): void {
            $table->string('signature_path', 255)->nullable();
        });

        Schema::table('assessment_reports', function (Blueprint $table): void {
            $table->string('signer_signature_path', 255)->nullable();
        });

        Schema::table('assessment_report_revisions', function (Blueprint $table): void {
            $table->string('signer_signature_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_report_revisions', function (Blueprint $table): void {
            $table->dropColumn('signer_signature_path');
        });

        Schema::table('assessment_reports', function (Blueprint $table): void {
            $table->dropColumn('signer_signature_path');
        });

        Schema::table('assessment_psychologists', function (Blueprint $table): void {
            $table->dropColumn('signature_path');
        });

        Schema::table('assessment_providers', function (Blueprint $table): void {
            $table->dropColumn(['address', 'practice_licence_number', 'phone', 'email', 'logo_path']);
        });
    }
};
