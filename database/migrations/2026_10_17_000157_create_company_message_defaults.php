<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-interview-calendar.md` section 6 and `phase-8-recruiter-workday.md` section 5: the default texts a company starts its messages with (one row per
 * company, a column per scenario), and the note for the candidate on a test invitation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_message_defaults', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('company_companies')->restrictOnDelete();
            $table->text('interview')->nullable();
            $table->text('assessment')->nullable();
            $table->text('offer')->nullable();
            $table->text('applied')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::table('assessment_invitations', function (Blueprint $table): void {
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_invitations', function (Blueprint $table): void {
            $table->dropColumn('note');
        });

        Schema::dropIfExists('company_message_defaults');
    }
};
