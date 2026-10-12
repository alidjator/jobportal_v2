<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Domain\QuestionType;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The written and video answers an assessor has to score, with the scores given so far. */
final readonly class ListScoringAnswers
{
    public function __construct(private ScoringAccess $access) {}

    /**
     * @return list<ScoringItem>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-assessor`
     * @throws EntityNotFound
     * @throws StateConflict `attempt-not-submitted`
     */
    public function execute(Actor $actor, int $invitationId): array
    {
        $invitation = $this->access->invitation($actor, $invitationId);
        $attempt = $this->access->submittedAttempt($invitation);

        return array_map(
            static fn (array $entry): ScoringItem => new ScoringItem(
                (int) $entry['question']->id, $entry['question']->text, $entry['question']->points, $entry['question']->type === QuestionType::LongText ? (string) $entry['answer']->textValue : null,
                $entry['answer']->score, $entry['question']->type,
            ),
            $this->access->scorable($attempt),
        );
    }
}
