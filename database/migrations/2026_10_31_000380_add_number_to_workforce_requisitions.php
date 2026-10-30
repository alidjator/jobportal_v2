<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The number of a requisition (`docs/design/erp-requisitions.md` "Coded: the requisition number"): `workforce_requisitions.number`, unique per company, and the counters of the running
 * number per company and series. The requisitions that exist get a number by the default format (`JR-{YYYY}{MM}{DD}-{SEQ:3}`), in the order they were made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workforce_requisitions', function (Blueprint $table): void {
            $table->string('number', 60)->nullable();
        });

        Schema::create('workforce_requisition_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->string('series', 60);
            $table->unsignedInteger('last_number');

            $table->unique(['company_id', 'series'], 'workforce_requisition_counters_series_unique');
        });

        $last = [];

        foreach (DB::table('workforce_requisitions')->orderBy('created_at')->orderBy('id')->get(['id', 'company_id', 'created_at']) as $row) {
            $day = (new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'));
            $series = 'JR-'.$day->format('Ymd').'-';
            $last[$row->company_id][$series] = ($last[$row->company_id][$series] ?? 0) + 1;

            DB::table('workforce_requisitions')->where('id', $row->id)->update(['number' => $series.str_pad((string) $last[$row->company_id][$series], 3, '0', STR_PAD_LEFT)]);
        }

        foreach ($last as $companyId => $series) {
            foreach ($series as $name => $number) {
                DB::table('workforce_requisition_counters')->insert(['company_id' => $companyId, 'series' => $name, 'last_number' => $number]);
            }
        }

        Schema::table('workforce_requisitions', function (Blueprint $table): void {
            $table->unique(['company_id', 'number'], 'workforce_requisitions_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_requisition_counters');

        Schema::table('workforce_requisitions', function (Blueprint $table): void {
            $table->dropUnique('workforce_requisitions_number_unique');
            $table->dropColumn('number');
        });
    }
};
