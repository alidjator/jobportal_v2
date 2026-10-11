<?php

namespace Src\Campaign\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Campaign\Domain\Events\CampaignChanged;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Writes the audit entries for the Campaign context: a campaign created, changed or deleted, by a company admin (a user) or a platform admin. The entries hold ids,
 * never names. Runs on the `default` queue, retried 3 times with a growing delay.
 */
final class RecordCampaignAuditEntries implements ShouldQueue
{
    private const string SUBJECT_TYPE = 'campaign';

    public int $tries = 3;

    public function __construct(private readonly AuditTrail $trail) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function onCampaignChanged(CampaignChanged $event): void
    {
        $action = match ($event->change) {
            CampaignChanged::CREATED => AuditAction::CAMPAIGN_CREATED,
            CampaignChanged::UPDATED => AuditAction::CAMPAIGN_UPDATED,
            CampaignChanged::DELETED => AuditAction::CAMPAIGN_DELETED,
            default => throw new \LogicException("Unknown campaign change {$event->change}."),
        };

        $this->trail->record(new AuditEntry(
            $event->actorKind === 'admin' ? AuditActorType::Admin : AuditActorType::User, $event->actorId, $action, self::SUBJECT_TYPE, $event->campaignId,
            $event->companyId === null ? [] : ['company_id' => $event->companyId], $event->ipAddress, $event->userAgent, $event->occurredAt(),
        ));
    }
}
