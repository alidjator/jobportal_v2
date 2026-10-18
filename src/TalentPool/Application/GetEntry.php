<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Recruitment\PoolApplications;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\TalentPool\Domain\ConsentWindow;
use Src\TalentPool\Domain\EntryRepository;

/** One entry of the member's company's talent pool with its summary and, while the person is available, their profile and the applications the member may see. */
final readonly class GetEntry
{
    public function __construct(private TalentPoolAccess $access, private EntryRepository $entries, private EntryViews $views, private PoolApplications $applications, private Clock $clock) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $entryId, string $language): EntryDetail
    {
        $member = $this->access->member($actor);
        $entry = $this->entries->find($member->companyId, $entryId) ?? throw EntityNotFound::of('Talent pool entry', $entryId);
        $view = $this->views->views($actor, $member, [$entry], $language)[0];

        if (! $view->isAvailable()) {
            return new EntryDetail($view, null, []);
        }

        $profile = null;

        // A findable person is opened through the candidate search; only a person kept through consent alone has the snapshot of their application to show.
        if ($view->candidateSlug === null) {
            $profile = $this->applications->consentSnapshots($member->companyId, [$entry->candidateUserId], ConsentWindow::since($this->clock->now()), $language)[$entry->candidateUserId]->profile ?? null;
        }

        return new EntryDetail($view, $profile, $this->applications->visibleApplications($actor, $entry->candidateUserId));
    }
}
