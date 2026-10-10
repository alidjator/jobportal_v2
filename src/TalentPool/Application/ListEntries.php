<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\TalentPool\Domain\EntryRepository;

/** The candidates in the member's company's talent pool, the most recently saved first, optionally of one category. */
final readonly class ListEntries
{
    public function __construct(private TalentPoolAccess $access, private EntryRepository $entries, private EntryViews $views) {}

    /**
     * @return Page<EntryView>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor, ?int $categoryId, PageRequest $request): Page
    {
        $member = $this->access->member($actor);

        return $this->views->of($member->companyId, $this->entries->ofCompany($member->companyId, $categoryId, $request));
    }
}
