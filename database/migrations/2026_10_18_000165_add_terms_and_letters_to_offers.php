<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-offer-letters.md` sections 1 and 2: the terms of an offer and its letter PDF (all nullable, the offers sent before have neither), and the
 * uploads of a company's own letter that wait for the offer that uses them (deleted after 24 hours when unused).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_offers', function (Blueprint $table): void {
            // permanent, contract, internship, daily or freelance.
            $table->string('employment_type', 12)->nullable();
            $table->unsignedSmallInteger('contract_months')->nullable();
            $table->unsignedSmallInteger('probation_months')->nullable();
            // onsite, hybrid or remote.
            $table->string('work_mode', 10)->nullable();
            $table->string('work_location', 255)->nullable();
            // gross or net.
            $table->string('salary_basis', 5)->nullable();
            // [{name, amount}], at most 10 lines, Rupiah per month.
            $table->jsonb('allowances')->nullable();
            // [{id, name: {id, en}}]: a snapshot of the benefits as they were named when the offer was sent.
            $table->jsonb('benefits')->nullable();
            // The letter on the private disk, where it came from (generated or uploaded) and the SHA-256 of the exact file.
            $table->string('letter_path')->nullable();
            $table->string('letter_source', 10)->nullable();
            $table->string('letter_sha256', 64)->nullable();
        });

        Schema::create('recruitment_offer_letter_uploads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            // The member who uploaded it; no foreign key to accounts.
            $table->unsignedBigInteger('uploaded_by');
            $table->string('path');
            $table->unsignedInteger('size_bytes');
            $table->string('sha256', 64);
            $table->timestamp('created_at');

            $table->foreign('application_id', Schema::getConnection()->getTablePrefix().'recruitment_offer_letter_uploads_application_foreign')->references('id')->on('recruitment_applications')->cascadeOnDelete();
            $table->index('created_at', 'recruitment_offer_letter_uploads_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_offer_letter_uploads');

        Schema::table('recruitment_offers', function (Blueprint $table): void {
            $table->dropColumn(['employment_type', 'contract_months', 'probation_months', 'work_mode', 'work_location', 'salary_basis', 'allowances', 'benefits', 'letter_path', 'letter_source', 'letter_sha256']);
        });
    }
};
