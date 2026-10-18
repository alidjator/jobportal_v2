<?php

namespace Src\Content\Application\Announcements;

use Src\Content\Domain\Announcement;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;

/** Writes the audit entry of an announcement change: who, which announcement, its audience and level; never the text. */
final readonly class AnnouncementAudit
{
    public function __construct(private AuditTrail $audit, private Clock $clock) {}

    public function record(Actor $actor, string $action, Announcement $announcement, ?string $ipAddress, ?string $userAgent): void
    {
        $this->audit->record(new AuditEntry(
            AuditActorType::Admin, (int) $actor->id, $action, 'announcement', $announcement->id,
            ['audience' => $announcement->audience->value, 'level' => $announcement->level->value], $ipAddress, $userAgent, $this->clock->now(),
        ));
    }
}
