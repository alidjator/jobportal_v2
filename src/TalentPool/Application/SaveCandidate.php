<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\TalentPool\Domain\CategoryRepository;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntryRepository;

/**
 * A member saves a candidate they found (one who can be found right now) into their company's talent pool, optionally in one of the company's categories. A
 * candidate is saved once per company, whoever saved them; a company keeps at most 1,000 candidates.
 */
final readonly class SaveCandidate
{
    public const int MAX_ENTRIES = 1000;

    public function __construct(
        private TalentPoolAccess $access,
        private CandidateDirectory $candidates,
        private CategoryRepository $categories,
        private EntryRepository $entries,
        private EntryViews $views,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the candidate (missing or not findable) or the category (missing or another company's)
     * @throws BusinessRuleViolation `talent-pool-limit`
     * @throws StateConflict `already-in-talent-pool`
     */
    public function execute(Actor $actor, string $candidateSlug, ?int $categoryId): EntryView
    {
        $member = $this->access->member($actor);
        $candidate = $this->candidates->findable($candidateSlug) ?? throw EntityNotFound::of('Candidate', $candidateSlug);

        $entry = $this->transactions->run(function () use ($member, $candidate, $categoryId): Entry {
            if ($categoryId !== null) {
                $this->categories->find($member->companyId, $categoryId) ?? throw EntityNotFound::of('Category', $categoryId);
            }

            if ($this->entries->exists($member->companyId, $candidate->userId)) {
                throw new StateConflict('This candidate is already in your talent pool.', 'already-in-talent-pool');
            }

            if ($this->entries->count($member->companyId) >= self::MAX_ENTRIES) {
                throw new BusinessRuleViolation('A company keeps at most '.self::MAX_ENTRIES.' candidates in its talent pool.', 'talent-pool-limit');
            }

            return $this->entries->add(new Entry($member->companyId, $candidate->userId, $categoryId, $this->clock->now()));
        });

        return $this->views->views($member->companyId, [$entry])[0];
    }
}
