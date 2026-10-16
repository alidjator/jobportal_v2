<?php

namespace Src\Organization\Application\Reading;

use Src\Organization\Domain\Events\GroupRecruitmentRead;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;

/** Notes that a group member read applicants of a tenant, so the read is audited for the group and for the tenant. */
final readonly class RecordGroupRecruitmentRead
{
    public function __construct(private EventDispatcher $events, private Clock $clock) {}

    public function execute(int $userId, TenantRead $read, string $request, ?string $ipAddress, ?string $userAgent): void
    {
        $this->events->dispatch([new GroupRecruitmentRead($read->groupId, $read->companyId, $userId, $request, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
