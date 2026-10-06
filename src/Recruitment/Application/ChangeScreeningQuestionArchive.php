<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningQuestion;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A company admin archives a question (it can no longer be attached to jobs; the jobs that already use it keep it) or restores it. Doing it twice
 * changes nothing.
 */
final readonly class ChangeScreeningQuestionArchive
{
    public function __construct(
        private RecruitmentAccess $access,
        private ScreeningSwitch $switch,
        private ScreeningQuestionRepository $questions,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-admin-required` or `screening-disabled`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id, bool $archive): ScreeningQuestion
    {
        $member = $this->access->companyAdmin($actor);
        $this->switch->assertOn($member->companyId);
        $now = $this->clock->now();

        $this->transactions->run(function () use ($member, $id, $archive, $now): void {
            $this->questions->lock($id);
            $question = $this->questions->find($id);

            if ($question === null || $question->companyId !== $member->companyId) {
                throw EntityNotFound::of('Screening question', $id);
            }

            if ($question->isArchived() !== $archive) {
                $this->questions->setArchived($id, $archive ? $now : null, $now);
            }
        });

        return $this->questions->find($id) ?? throw EntityNotFound::of('Screening question', $id);
    }
}
