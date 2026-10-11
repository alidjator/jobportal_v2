<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\ColumnDigits;
use Src\Assessment\Domain\SectionFormat;
use Src\Assessment\Domain\SpeedColumnResult;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The candidate hands in a Kraepelin-type column: for each pair of neighbouring digits, the last digit of their sum (or null where they got no further). The
 * columns are timed slices of the section; a column is accepted while its slice runs and for a few seconds after, so the last answers are not lost to the network,
 * and only once. The server counts how many sums were attempted and how many were right.
 */
final readonly class SaveColumn
{
    /** Seconds after the end of a column during which its answers are still taken. */
    private const int GRACE_SECONDS = 3;

    public function __construct(
        private AttemptGate $gate,
        private AnswerRepository $answers,
    ) {}

    /**
     * @param  list<int|null>  $sums
     *
     * @throws BusinessRuleViolation `invalid-column`
     * @throws StateConflict `attempt-not-started`, `attempt-closed` or `column-already-submitted`
     */
    public function execute(Actor $actor, int $invitationId, int $columnNo, array $sums, ?string $ip): void
    {
        $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($columnNo, $sums): void {
            $section = $context->section();

            if ($section->format !== SectionFormat::SpeedColumns) {
                throw $this->invalid('column', 'The open section has no columns.');
            }

            $columns = $section->settings['columns'];
            $seconds = $section->settings['seconds_per_column'];

            if ($columnNo < 1 || $columnNo > $columns) {
                throw $this->invalid('column', 'Choose a column of the section.');
            }

            $opened = $context->attempt->sectionOpenedAt->getTimestamp();
            $endsAt = $opened + $columnNo * $seconds;

            if ($context->now->getTimestamp() < $endsAt - $seconds || $context->now->getTimestamp() > $endsAt + self::GRACE_SECONDS) {
                throw $this->invalid('column', 'This column is not open now.');
            }

            $expected = ColumnDigits::expected(ColumnDigits::of($context->attempt->seed, (int) $section->id, $columnNo, $section->settings['digits_per_column']));

            if (count($sums) > count($expected)) {
                throw $this->invalid('answers', 'The column has '.count($expected).' sums.');
            }

            $attemptId = (int) $context->attempt->id;

            foreach ($this->answers->columnsOf($attemptId) as $result) {
                if ($result->sectionId === $section->id && $result->columnNo === $columnNo) {
                    throw new StateConflict('This column was already handed in.', 'column-already-submitted');
                }
            }

            $attempted = 0;
            $correct = 0;

            foreach ($sums as $index => $sum) {
                if ($sum === null) {
                    continue;
                }

                $attempted++;
                $correct += $sum === $expected[$index] ? 1 : 0;
            }

            $this->answers->saveColumn($attemptId, new SpeedColumnResult((int) $section->id, $columnNo, $attempted, $correct));
        });
    }

    private function invalid(string $field, string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-column', extensions: ['errors' => [$field => [$message]]]);
    }
}
