<?php

namespace Src\Messaging\Domain\Events;

use DateTimeImmutable;
use Src\Messaging\Domain\ReportStatus;
use Src\Shared\Domain\DomainEvent;

/** An admin decided a conversation report. `reporterUserId` is null once the reporter's account was erased (nobody is told). */
final readonly class MessageReportDecided implements DomainEvent
{
    public function __construct(
        public int $reportId,
        public int $conversationId,
        public ?int $reporterUserId,
        public ReportStatus $outcome,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
