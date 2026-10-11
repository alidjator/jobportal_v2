<?php

namespace Src\Assessment\Domain;

/**
 * The questions of a section that one attempt is asked. A section that draws takes that many from its bank at random; the draw only depends on the
 * attempt's seed, so it never changes, and the questions keep the order of the bank.
 */
final class QuestionDraw
{
    /**
     * @param  list<Question>  $bank  by position
     * @return list<Question>
     */
    public static function pick(array $bank, ?int $drawCount, int $seed, int $sectionId): array
    {
        if ($drawCount === null || $drawCount >= count($bank)) {
            return $bank;
        }

        $ranked = $bank;
        usort($ranked, static fn (Question $a, Question $b): int => self::rank($seed, $sectionId, (int) $a->id) <=> self::rank($seed, $sectionId, (int) $b->id));
        $chosen = array_map(static fn (Question $question): int => (int) $question->id, array_slice($ranked, 0, $drawCount));

        return array_values(array_filter($bank, static fn (Question $question): bool => in_array((int) $question->id, $chosen, true)));
    }

    private static function rank(int $seed, int $sectionId, int $questionId): string
    {
        return hash('sha256', "{$seed}:{$sectionId}:{$questionId}");
    }
}
