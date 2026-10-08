<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_application_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('application_id');
            // The member that wrote the note; no foreign key to accounts.
            $table->unsignedBigInteger('author_id');
            $table->text('body');
            $table->timestamps();
            // Set when the author changes the text, so readers can tell an edited note.
            $table->timestamp('edited_at')->nullable();

            $table->foreign('application_id', Schema::getConnection()->getTablePrefix().'recruitment_notes_application_foreign')->references('id')->on('recruitment_applications')->cascadeOnDelete();

            $table->index(['application_id', 'created_at'], 'recruitment_notes_application_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_notes');
    }
};
