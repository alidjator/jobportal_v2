<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\TalentPool\Domain\EntryRepository;

/** A member removes a candidate from the company's talent pool, including one who can no longer be found. Removing one that is not there changes nothing. */
final readonly class RemoveEntry
{
    public function __construct(private TalentPoolAccess $access, private EntryRepository $entries) {}

    /** @throws AccessDenied `not-a-company-member` or `company-suspended` */
    public function execute(Actor $actor, int $entryId): void
    {
        $this->entries->remove($this->access->member($actor)->companyId, $entryId);
    }
}
