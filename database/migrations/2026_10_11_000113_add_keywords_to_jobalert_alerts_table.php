<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The words of the alert's keyword (`criteria.q`), split and de-duplicated as the job search does, so one SQL statement can match
        // a live job against every immediate alert.
        Schema::table('jobalert_alerts', function (Blueprint $table): void {
            $table->jsonb('keywords')->default('[]')->after('criteria');
        });

        DB::table('jobalert_alerts')->whereRaw("criteria->>'q' IS NOT NULL")->orderBy('id')->chunkById(500, function ($alerts): void {
            foreach ($alerts as $alert) {
                $criteria = json_decode((string) $alert->criteria, true);
                $words = [];

                foreach (preg_split('/\s+/u', trim(is_string($criteria['q'] ?? null) ? $criteria['q'] : ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                    $words[mb_strtolower($word)] = $word;
                }

                DB::table('jobalert_alerts')->where('id', $alert->id)->update(['keywords' => json_encode(array_keys($words))]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('jobalert_alerts', fn (Blueprint $table) => $table->dropColumn('keywords'));
    }
};
