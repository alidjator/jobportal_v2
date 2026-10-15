<?php

namespace Src\Organization\Infrastructure\Listeners;

use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Organization\Domain\GroupMemberRepository;

/** A group role sits on top of the company membership, so it ends when the membership does (`docs/design/erp-organization.md` section 3). Idempotent. */
final class RemoveGroupRoleWhenMemberRemoved
{
    public function __construct(private readonly GroupMemberRepository $members) {}

    public function handle(CompanyMemberRemoved $event): void
    {
        $this->members->delete($event->userId);
    }
}
