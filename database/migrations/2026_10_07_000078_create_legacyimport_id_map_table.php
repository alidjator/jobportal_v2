<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacyimport_id_map', function (Blueprint $table): void {
            $table->id();
            // The kind of row (`user`, `company`, `job`, `application`, `resume`, ...). The ids belong to other contexts and to another database: no foreign keys.
            $table->string('entity', 40);
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamp('imported_at');

            $table->unique(['entity', 'legacy_id'], 'legacyimport_id_map_legacy_unique');
            $table->index(['entity', 'new_id'], 'legacyimport_id_map_new_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacyimport_id_map');
    }
};
