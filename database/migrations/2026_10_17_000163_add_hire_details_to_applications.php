<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-hiring.md` section 1: the join date and the note for the candidate of a hired application (empty for any other stage). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->date('join_date')->nullable();
            $table->string('hire_note', 1000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->dropColumn(['join_date', 'hire_note']);
        });
    }
};
