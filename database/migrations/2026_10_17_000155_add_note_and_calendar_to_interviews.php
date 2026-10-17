<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-interview-calendar.md` sections 2 and 3: the note for the candidate, and the calendar identity of an interview chain (one UID for all the
 * interviews of an application, a sequence that rises with every change), so a calendar app moves or cancels one event instead of adding a second.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->text('note')->nullable();
            $table->string('calendar_uid', 36)->nullable();
            $table->unsignedInteger('calendar_sequence')->default(0);
        });

        $interviews = DB::getTablePrefix().'recruitment_interviews';

        // One UID per application, shared by its interviews; the sequence follows their order, so the latest one is the highest.
        DB::statement(<<<SQL
            UPDATE "{$interviews}" AS i SET calendar_uid = c.uid
            FROM (SELECT application_id, gen_random_uuid()::text AS uid FROM (SELECT DISTINCT application_id FROM "{$interviews}") AS d) AS c
            WHERE c.application_id = i.application_id
        SQL);
        DB::statement(<<<SQL
            UPDATE "{$interviews}" AS i SET calendar_sequence = r.position
            FROM (SELECT id, ROW_NUMBER() OVER (PARTITION BY application_id ORDER BY created_at, id) AS position FROM "{$interviews}") AS r
            WHERE r.id = i.id
        SQL);

        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->string('calendar_uid', 36)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_interviews', function (Blueprint $table): void {
            $table->dropColumn(['note', 'calendar_uid', 'calendar_sequence']);
        });
    }
};
