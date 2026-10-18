<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Recruitment\PoolApplications;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\TalentPool\Domain\ConsentWindow;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntrySource;

/**
 * A member saves the applicant of an application they may see (`docs/design/phase-8-talent-pool.md` section 1). It works from every stage except `hired`, and only when the
 * applicant allowed it (a consent given with that application, still inside the 24 months) or can be found anyway. The entry remembers the consenting application.
 */
final readonly class SaveApplicant
{
    public function __construct(
        private TalentPoolAccess $access,
        private TeamApplications $teamApplications,
        private PoolApplications $applications,
        private CandidateDirectory $candidates,
        private NewEntries $newEntries,
        private EntryViews $views,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the application (missing, another company's or not the member's to see) or the category (missing or another company's)
     * @throws BusinessRuleViolation `note-too-long` or `talent-pool-limit`
     * @throws StateConflict `application-hired`, `talent-pool-consent-missing` or `already-in-talent-pool`
     */
    public function execute(Actor $actor, int $applicationId, ?int $categoryId, ?string $note, string $language): EntryView
    {
        $member = $this->access->member($actor);
        $note = Entry::cleanNote($note);
        $ref = $this->teamApplications->find($actor, $applicationId);
        $facts = $this->applications->facts($applicationId) ?? throw EntityNotFound::of('Application', $applicationId);
        $now = $this->clock->now();

        if ($facts->hired) {
            throw new StateConflict('A hired applicant cannot be saved to the talent pool.', 'application-hired');
        }

        $consenting = $facts->consentGiven && $facts->appliedAt >= ConsentWindow::since($now);

        if (! $consenting && $this->candidates->findableIds([$ref->applicantUserId]) === []) {
            throw new StateConflict('The applicant did not allow the company to keep their profile in a talent pool.', 'talent-pool-consent-missing');
        }

        $entry = $this->transactions->run(fn (): Entry => $this->newEntries->add(
            new Entry($member->companyId, $ref->applicantUserId, $categoryId, $now, note: $note, source: EntrySource::Application, applicationId: $consenting ? $applicationId : null, savedByUserId: $member->userId),
        ));

        return $this->views->views($actor, $member, [$entry], $language)[0];
    }
}
