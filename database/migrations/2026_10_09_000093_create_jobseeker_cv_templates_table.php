<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_cv_templates', function (Blueprint $table): void {
            // The set of templates is fixed in code (`CvTemplate`); this table only holds whether each is on and which is the default.
            $table->string('key', 30)->primary();
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('updated_at')->nullable();
        });

        $now = now();

        DB::table('jobseeker_cv_templates')->insert([
            ['key' => 'classic', 'enabled' => true, 'is_default' => true, 'updated_at' => $now],
            ['key' => 'modern', 'enabled' => true, 'is_default' => false, 'updated_at' => $now],
            ['key' => 'compact', 'enabled' => true, 'is_default' => false, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_cv_templates');
    }
};
