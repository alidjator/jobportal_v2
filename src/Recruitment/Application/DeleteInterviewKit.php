<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewKitRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Deletes a kit. Past interviews keep their copy of the questions, and the job defaults that named the kit go with it. */
final readonly class DeleteInterviewKit
{
    public function __construct(
        private RecruitmentAccess $access,
        private InterviewKitLookup $lookup,
        private InterviewKitRepository $kits,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $kitId): void
    {
        $kit = $this->lookup->find($this->access->member($actor), $kitId);

        $this->transactions->run(fn () => $this->kits->delete((int) $kit->id));
    }
}
