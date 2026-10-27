<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-provider-institutions.md` section 2: a psychologist submits a renewed STR or SILP with the document; the platform admin approves it (the number and
 * date go onto the psychologist) or rejects it. One pending submission per psychologist and kind. The documents are kept while the psychologist exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_licence_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('psychologist_id')->constrained('assessment_psychologists')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('number', 60);
            $table->date('valid_until');
            $table->string('document_path', 255);
            $table->string('document_name', 255);
            $table->unsignedInteger('document_size');
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('identity_admins')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['psychologist_id', 'created_at']);
        });

        $connection = Schema::getConnection();
        $name = $connection->getTablePrefix().'assessment_licence_submissions';

        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_kind_check\" CHECK (\"kind\" IN ('str', 'silp'))");
        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_status_check\" CHECK (\"status\" IN ('pending', 'approved', 'rejected'))");
        $connection->statement("CREATE UNIQUE INDEX \"{$name}_one_pending\" ON \"{$name}\" (\"psychologist_id\", \"kind\") WHERE \"status\" = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_licence_submissions');
    }
};
