<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `docs/design/phase-8-interview-results.md` section 2: optional texts that say what the scores 1, 3 and 5 mean, on the kit questions and on the copy an interview keeps. */
return new class extends Migration
{
    private const array TABLES = ['recruitment_interview_kit_questions', 'recruitment_interview_questions'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('anchor_1', 200)->nullable();
                $table->string('anchor_3', 200)->nullable();
                $table->string('anchor_5', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['anchor_1', 'anchor_3', 'anchor_5']);
            });
        }
    }
};
