<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-activation.md` sections 7 and 11: the pending self-service email change of an account (one per account). Only the hashes of the codes are stored; the row is deleted
 * when the change is confirmed or cancelled and by the account eraser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_email_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('identity_users')->restrictOnDelete();
            $table->string('new_email');
            $table->string('new_code_hash');
            $table->string('old_code_hash')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_email_changes');
    }
};
