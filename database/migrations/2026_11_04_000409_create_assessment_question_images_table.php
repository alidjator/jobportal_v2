<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-question-images.md`: the pictures of a question (up to a few, in order) and of its options (one each). An image is uploaded to the test first and attached when the question is saved, so
 * `question_id` is null until then; an unattached image older than a day is purged. Options have no id (they are replaced on every save), so an option's image is named by its position.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_question_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_id')->constrained('assessment_tests')->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('assessment_questions')->cascadeOnDelete();
            $table->unsignedSmallInteger('option_position')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('path', 255);
            $table->string('content_type', 50);
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('alt', 300);
            $table->timestamps();

            $table->index('test_id', 'assessment_question_images_test_index');
            $table->index('question_id', 'assessment_question_images_question_index');
        });

        $connection = Schema::getConnection();
        $table = $connection->getTablePrefix().'assessment_question_images';

        // One image per place: the question's own images by their order, an option's image by its position.
        $connection->statement("CREATE UNIQUE INDEX \"{$table}_place_unique\" ON \"{$table}\" (\"question_id\", COALESCE(\"option_position\", 0), \"position\") WHERE \"question_id\" IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_question_images');
    }
};
