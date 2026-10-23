<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The default stages used to start with English names. Only a default stage that still holds its old name is renamed: a stage the company renamed keeps its name, and a stage is
 * skipped when another stage of the same company already uses the new name (names are unique per company, ignoring case).
 */
return new class extends Migration
{
    private const array NAMES = [
        'applied' => ['Applied', 'Dilamar'],
        'screening' => ['Screening', 'Penyaringan'],
        'assessment' => ['Assessment', 'Asesmen'],
        'interview' => ['Interview', 'Wawancara'],
        'offer' => ['Offer', 'Penawaran'],
        'hired' => ['Hired', 'Diterima'],
        'rejected' => ['Rejected', 'Ditolak'],
        'withdrawn' => ['Withdrawn', 'Mengundurkan diri'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $type => [$old, $new]) {
            $this->rename($type, $old, $new);
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $type => [$old, $new]) {
            $this->rename($type, $new, $old);
        }
    }

    private function rename(string $type, string $from, string $to): void
    {
        DB::statement(
            'UPDATE "recruitment_stages" AS "s" SET "name" = ?, "updated_at" = now() '
            .'WHERE "s"."is_default" AND "s"."type" = ? AND "s"."name" = ? '
            .'AND NOT EXISTS (SELECT 1 FROM "recruitment_stages" AS "o" WHERE "o"."company_id" = "s"."company_id" AND "o"."id" <> "s"."id" AND lower("o"."name") = lower(?))',
            [$to, $type, $from, $to],
        );
    }
};
