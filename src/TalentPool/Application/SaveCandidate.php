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
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntrySource;

/**
 * A member saves a candidate they found (one who can be found right now) into their company's talent pool, optionally in one of the company's categories and
 * with a note of up to 500 characters that every member of the company sees. A candidate is saved once per company, whoever saved them; a company keeps at most 1,000
 * candidates (`NewEntries`).
 */
final readonly class SaveCandidate
{
    public function __construct(
        private TalentPoolAccess $access,
        private CandidateDirectory $candidates,
        private NewEntries $newEntries,
        private EntryViews $views,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the candidate (missing or not findable) or the category (missing or another company's)
     * @throws BusinessRuleViolation `note-too-long` or `talent-pool-limit`
     * @throws StateConflict `already-in-talent-pool`
     */
    public function execute(Actor $actor, string $candidateSlug, ?int $categoryId, ?string $note, string $language): EntryView
    {
        $member = $this->access->member($actor);
        $note = Entry::cleanNote($note);
        $candidate = $this->candidates->findable($candidateSlug) ?? throw EntityNotFound::of('Candidate', $candidateSlug);

        $entry = $this->transactions->run(fn (): Entry => $this->newEntries->add(
            new Entry($member->companyId, $candidate->userId, $categoryId, $this->clock->now(), note: $note, source: EntrySource::Search, savedByUserId: $member->userId),
        ));

        return $this->views->views($actor, $member, [$entry], $language)[0];
    }
}
