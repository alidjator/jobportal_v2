<?php

namespace Src\Platform\Application\EmailSuppression;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** An admin holding `settings.manage` lifts a suppression (the person fixed the mailbox, or it was entered by mistake). Audited with the entry id and its reason. */
final readonly class RemoveEmailSuppression
{
    public function __construct(
        private EmailSuppressionRepository $suppressions,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the entry does not exist
     */
    public function execute(Actor $actor, int $id, ?string $ipAddress, ?string $userAgent): void
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can lift an email suppression.');
        }

        $entry = $this->suppressions->find($id) ?? throw EntityNotFound::of('EmailSuppression', $id);
        $this->suppressions->delete($id);

        $this->audit->record(new AuditEntry(
            AuditActorType::Admin, (int) $actor->id, AuditAction::EMAIL_SUPPRESSION_REMOVED, 'email_suppression', $id,
            ['reason' => $entry->reason->value], $ipAddress, $userAgent, $this->clock->now(),
        ));
    }
}
