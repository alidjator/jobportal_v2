<?php

namespace Src\Assessment\Domain;

/**
 * The questions of a section that one attempt is asked. A section that draws takes that many from its bank at random; the draw only depends on the
 * attempt's seed, so it never changes, and the questions keep the order of the bank. Sample questions are for practice (`docs/design/phase-8-candidate-experience.md`
 * section 4) and are not drawn, unless the bank without them is too short for the section: it keeps drawing them until its author fixes it.
 */
final class QuestionDraw
{
    /**
     * @param  list<Question>  $bank  by position
     * @return list<Question>
     */
    public static function pick(array $bank, ?int $drawCount, int $seed, int $sectionId): array
    {
        $bank = self::pool($bank, $drawCount);

        if ($drawCount === null || $drawCount >= count($bank)) {
            return $bank;
        }

        $ranked = $bank;
        usort($ranked, static fn (Question $a, Question $b): int => self::rank($seed, $sectionId, (int) $a->id) <=> self::rank($seed, $sectionId, (int) $b->id));
        $chosen = array_map(static fn (Question $question): int => (int) $question->id, array_slice($ranked, 0, $drawCount));

        return array_values(array_filter($bank, static fn (Question $question): bool => in_array((int) $question->id, $chosen, true)));
    }

    /**
     * The questions an attempt may be asked: the bank without its samples, or the whole bank while the questions that are not samples cannot fill the section (`drawCount`, or at least one).
     *
     * @param  list<Question>  $bank  by position
     * @return list<Question>
     */
    public static function pool(array $bank, ?int $drawCount): array
    {
        $others = array_values(array_filter($bank, static fn (Question $question): bool => ! $question->isSample));

        return count($others) >= max(1, $drawCount ?? 1) ? $others : $bank;
    }

    /** Whether a section draws its samples for want of other questions (the authoring view flags it). */
    public static function drawsSamples(int $questions, int $others, ?int $drawCount): bool
    {
        return $questions > $others && $others < max(1, $drawCount ?? 1);
    }

    private static function rank(int $seed, int $sectionId, int $questionId): string
    {
        return hash('sha256', "{$seed}:{$sectionId}:{$questionId}");
    }
}
