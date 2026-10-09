<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('messaging_conversations')->cascadeOnDelete();
            // company or seeker.
            $table->string('sender_side', 10);
            // The sender's account; no foreign key to accounts.
            $table->unsignedBigInteger('sender_user_id');
            // Null for a message of files only, after the sender deleted it, and after the sender's account was anonymised.
            $table->text('body')->nullable();
            // Set when the sender deleted the message: the row stays so the thread keeps its order and the company's daily count.
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('created_at');

            $table->index(['conversation_id', 'id'], 'messaging_messages_conversation_index');
            $table->index(['sender_user_id'], 'messaging_messages_sender_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_messages');
    }
};
