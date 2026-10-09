<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_attachments', function (Blueprint $table): void {
            $table->id();
            // The account that uploaded the file (the sender); erased with the account.
            $table->unsignedBigInteger('owner_user_id');
            $table->string('name', 100);
            // A random path on the private disk.
            $table->string('path', 255);
            // application/pdf, image/jpeg or image/png, read from the content.
            $table->string('mime', 30);
            $table->unsignedInteger('size_bytes');
            $table->dateTime('created_at');

            $table->index('owner_user_id', 'messaging_attachments_owner_index');
        });

        // A bulk message shares its files: one stored file is linked to many messages and deleted when its last link goes.
        Schema::create('messaging_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('messaging_messages')->cascadeOnDelete();
            $table->foreignId('attachment_id')->constrained('messaging_attachments')->cascadeOnDelete();

            $table->unique(['message_id', 'attachment_id'], 'messaging_message_attachments_unique');
            $table->index('attachment_id', 'messaging_message_attachments_attachment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_message_attachments');
        Schema::dropIfExists('messaging_attachments');
    }
};
