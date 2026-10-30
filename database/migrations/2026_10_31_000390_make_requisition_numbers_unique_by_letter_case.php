<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** `docs/design/erp-requisitions.md` "Coded: the requisition number": a company that types its numbers cannot use one twice, `JR-1` and `jr-1` being the same. */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = Schema::getConnection()->getTablePrefix();

        Schema::getConnection()->statement("CREATE UNIQUE INDEX \"{$prefix}workforce_requisitions_number_ci_unique\" ON \"{$prefix}workforce_requisitions\" (\"company_id\", lower(\"number\"))");
    }

    public function down(): void
    {
        $prefix = Schema::getConnection()->getTablePrefix();

        Schema::getConnection()->statement("DROP INDEX IF EXISTS \"{$prefix}workforce_requisitions_number_ci_unique\"");
    }
};
