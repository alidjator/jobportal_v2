<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_screening_presets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->string('name', 100);
            // Only a hint for the job edit screen: it never restricts which preset a job can use.
            $table->foreignId('job_level_id')->nullable()->constrained('reference_job_levels')->restrictOnDelete();
            // The account is a plain column: accounts live in another context and have no foreign key.
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'recruitment_screening_presets';

        $connection->statement(
            "CREATE UNIQUE INDEX \"recruitment_screening_presets_company_name_unique\" ON \"{$tableName}\" (\"company_id\", lower(\"name\"))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_screening_presets');
    }
};
