<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_permission_presets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 60);
            // The names of the permissions in catalog order: a preset is a saved starting point, copied to a member when it is applied.
            $table->json('permissions');
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            $table->index('company_id', 'company_permission_presets_company_index');
        });

        $connection = Schema::getConnection();
        $tableName = $connection->getTablePrefix().'company_permission_presets';

        // One preset name per company, whatever the case.
        $connection->statement('CREATE UNIQUE INDEX "company_permission_presets_name_unique" ON "'.$tableName.'" ("company_id", lower("name"))');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_permission_presets');
    }
};
