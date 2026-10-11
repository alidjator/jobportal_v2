<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The partner institutions that own psychological batteries (`docs/design/phase-6-assessments.md`).
        Schema::create('assessment_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('legal_name', 200);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        // A psychologist of a provider. `user_id` is the account that signs in (type `psychologist`); the licence numbers and their
        // validity dates decide whether the psychologist may sign reports.
        Schema::create('assessment_psychologists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('assessment_providers')->restrictOnDelete();
            $table->string('str_number', 60);
            $table->date('str_valid_until');
            $table->string('silp_number', 60);
            $table->date('silp_valid_until');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['provider_id', 'status']);
        });

        // A pending, used or revoked invitation to become a psychologist of a provider. The record holds what the admin entered; the
        // psychologist row is created when the invitation is accepted. Only the hash of the token is stored.
        Schema::create('assessment_psychologist_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('assessment_providers')->restrictOnDelete();
            $table->string('email', 255);
            $table->string('name', 150);
            $table->string('str_number', 60);
            $table->date('str_valid_until');
            $table->string('silp_number', 60);
            $table->date('silp_valid_until');
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by_admin_id')->constrained('identity_admins')->restrictOnDelete();
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'email']);
        });

        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        // A provider name is unique ignoring case (the application checks it first, this is the safety net).
        $connection->statement("CREATE UNIQUE INDEX \"{$prefix}assessment_providers_name_unique\" ON \"{$prefix}assessment_providers\" (lower(\"name\"))");

        foreach (['assessment_providers', 'assessment_psychologists'] as $table) {
            $name = $prefix.$table;
            $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_status_check\" CHECK (\"status\" IN ('active', 'suspended'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_psychologist_invitations');
        Schema::dropIfExists('assessment_psychologists');
        Schema::dropIfExists('assessment_providers');
    }
};
