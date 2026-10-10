<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A company's own labels for the candidates it saved ("Designers", "Call back in spring"); the same name twice is refused whatever its case.
        Schema::create('talentpool_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->string('name', 50);
            $table->timestamps();
        });

        // A candidate a company saved: one row per company per candidate, shared by every member. Deleting a category keeps its entries (they become uncategorised).
        Schema::create('talentpool_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('company_companies')->restrictOnDelete();
            $table->foreignId('candidate_user_id')->constrained('identity_users')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('talentpool_categories')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'candidate_user_id'], 'talentpool_entries_company_candidate_unique');
            $table->index(['company_id', 'category_id', 'created_at'], 'talentpool_entries_list_index');
            $table->index('candidate_user_id', 'talentpool_entries_candidate_index');
        });

        $connection = Schema::getConnection();
        $categories = $connection->getTablePrefix().'talentpool_categories';

        $connection->statement("CREATE UNIQUE INDEX \"talentpool_categories_name_unique\" ON \"{$categories}\" (\"company_id\", lower(\"name\"))");
    }

    public function down(): void
    {
        Schema::dropIfExists('talentpool_entries');
        Schema::dropIfExists('talentpool_categories');
    }
};
