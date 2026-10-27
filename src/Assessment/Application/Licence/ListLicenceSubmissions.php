<?php

namespace Src\Assessment\Application\Licence;

use Src\Assessment\Application\Admin\AdminOnly;
use Src\Assessment\Domain\LicenceStatus;
use Src\Assessment\Domain\LicenceSubmissionRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The admin's queue of licence submissions: the pending ones first, oldest first. */
final readonly class ListLicenceSubmissions
{
    public function __construct(
        private LicenceSubmissionRepository $submissions,
        private PsychologistAccounts $accounts,
    ) {}

    /**
     * @return Page<LicenceQueueItem>
     *
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor, ?LicenceStatus $status, PageRequest $request): Page
    {
        AdminOnly::assert($actor, 'list licence submissions');

        $page = $this->submissions->queue($status, $request);
        $summaries = $this->accounts->summaries(array_map(fn ($row): int => $row->psychologistUserId, $page->items));

        return new Page(
            array_map(fn ($row): LicenceQueueItem => new LicenceQueueItem($row, $summaries[$row->psychologistUserId] ?? null), $page->items),
            $page->total,
            $page->request,
        );
    }
}
