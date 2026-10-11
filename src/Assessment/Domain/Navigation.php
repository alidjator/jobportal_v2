<?php

namespace Src\Assessment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What a section lets the candidate do between its questions. With `allow_jump` any question can be opened and answered at any time. Without it the
 * candidate goes one question forward at a time, and `allow_back` also lets them return to an earlier question (earlier questions are locked otherwise).
 * `allow_skip` off means the question on screen must be answered before the next one opens. The rules are checked on the server; the browser only draws them.
 */
final class Navigation
{
    /** @param  int  $cursor  the question on screen (0-based) */
    public static function canAnswer(Section $section, int $cursor, int $index): bool
    {
        return $section->allowJump || $index === $cursor || ($section->allowBack && $index < $cursor);
    }

    /** The indexes of the questions the candidate may see now, given how many the section asks. @return list<int> */
    public static function visible(Section $section, int $cursor, int $count): array
    {
        if ($count === 0) {
            return [];
        }

        return match (true) {
            $section->allowJump => range(0, $count - 1),
            $section->allowBack => range(0, min($cursor, $count - 1)),
            default => [min($cursor, $count - 1)],
        };
    }

    /**
     * @throws BusinessRuleViolation `invalid-navigation`
     */
    public static function assertCanMove(Section $section, int $cursor, int $target, int $count, bool $currentAnswered): void
    {
        if ($target < 0 || $target >= $count || $target === $cursor) {
            throw self::refuse('Choose another question of this section.');
        }

        if ($section->allowJump) {
            return;
        }

        if ($target < $cursor) {
            if (! $section->allowBack) {
                throw self::refuse('This section does not let you go back.');
            }

            return;
        }

        if ($target !== $cursor + 1) {
            throw self::refuse('This section does not let you jump ahead.');
        }

        if (! $section->allowSkip && ! $currentAnswered) {
            throw self::refuse('Answer this question before you go on: this section does not let you skip.');
        }
    }

    private static function refuse(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-navigation', extensions: ['errors' => ['index' => [$message]]]);
    }
}
