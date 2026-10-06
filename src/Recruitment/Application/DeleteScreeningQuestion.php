<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company admin deletes a question that no job uses and nothing depends on; any other question can only be archived. */
final readonly class DeleteScreeningQuestion
{
    public function __construct(
        private RecruitmentAccess $access,
        private ScreeningSwitch $switch,
        private ScreeningQuestionRepository $questions,
        private ScreeningQuestionUsage $usage,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-admin-required` or `screening-disabled`
     * @throws EntityNotFound
     * @throws StateConflict `question-in-use`
     */
    public function execute(Actor $actor, int $id): void
    {
        $member = $this->access->companyAdmin($actor);
        $this->switch->assertOn($member->companyId);

        $this->transactions->run(function () use ($member, $id): void {
            $this->questions->lock($id);
            $question = $this->questions->find($id);

            if ($question === null || $question->companyId !== $member->companyId) {
                throw EntityNotFound::of('Screening question', $id);
            }

            if ($this->usage->isLocked($id) || $this->questions->isAttached($id)) {
                throw new StateConflict('This question is attached to a job or has answers, so it can only be archived.', 'question-in-use');
            }

            $this->questions->delete($id);
        });
    }
}
