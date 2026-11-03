<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * `docs/design/phase-8-disc-profile.md` (#47): the question type `most_least` of the DISC family, in which the candidate marks the statement that is most like them and the one that is least like them in each group.
 * The answer keeps `option_positions` as `[most, least]` and the statements are the options with their dimension in `scale_key`, so only the check on the type changes.
 */
return new class extends Migration
{
    private const array QUESTION_TYPES = ['single_choice', 'multiple_response', 'true_false', 'number', 'short_text', 'long_text', 'likert', 'forced_choice', 'drawing', 'video_answer'];

    public function up(): void
    {
        $this->replace([...self::QUESTION_TYPES, 'most_least']);
    }

    public function down(): void
    {
        $this->replace(self::QUESTION_TYPES);
    }

    /** @param  list<string>  $values */
    private function replace(array $values): void
    {
        $connection = Schema::getConnection();
        $name = $connection->getTablePrefix().'assessment_questions';

        $connection->statement("ALTER TABLE \"{$name}\" DROP CONSTRAINT IF EXISTS \"{$name}_type_check\"");
        $connection->statement("ALTER TABLE \"{$name}\" ADD CONSTRAINT \"{$name}_type_check\" CHECK (\"type\" IN ('".implode("', '", $values)."'))");
    }
};
