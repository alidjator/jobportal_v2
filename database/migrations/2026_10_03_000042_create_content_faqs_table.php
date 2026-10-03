<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_faqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('content_faq_categories')->cascadeOnDelete();
            $table->json('question');
            $table->json('answer');
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_faqs');
    }
};
