<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_conversation_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('messaging_conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            // The newest message this user has read; messages of the other side after it are unread for them.
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            // Since when this user has unread messages they were told about; null once they read the thread.
            $table->dateTime('unread_since')->nullable();
            // When the one reminder email of this unread stretch was sent; null until then and again once they read the thread.
            $table->dateTime('emailed_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'messaging_conversation_reads_unique');
            $table->index(['unread_since', 'emailed_at'], 'messaging_conversation_reads_email_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_conversation_reads');
    }
};
