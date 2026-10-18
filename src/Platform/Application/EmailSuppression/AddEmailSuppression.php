<?php

namespace Src\Platform\Application\EmailSuppression;

use LogicException;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\StateConflict;

/** An admin holding `settings.manage` suppresses an address by hand. The audit entry holds the entry id, never the address. */
final readonly class AddEmailSuppression
{
    public const string SOURCE = 'admin';

    public function __construct(
        private EmailSuppressionRepository $suppressions,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws StateConflict `already-suppressed`
     */
    public function execute(Actor $actor, string $email, ?string $details, ?string $ipAddress, ?string $userAgent): EmailSuppression
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can suppress an email address.');
        }

        $email = mb_strtolower(trim($email));
        $now = $this->clock->now();

        $id = $this->suppressions->add($email, EmailSuppressionReason::Manual, self::SOURCE, $details, $now)
            ?? throw new StateConflict('This address is already suppressed.', 'already-suppressed');
        $entry = $this->suppressions->find($id) ?? throw new LogicException('The suppression just added is missing.');

        $this->audit->record(new AuditEntry(
            AuditActorType::Admin, (int) $actor->id, AuditAction::EMAIL_SUPPRESSION_ADDED, 'email_suppression', $entry->id,
            ['reason' => EmailSuppressionReason::Manual->value], $ipAddress, $userAgent, $now,
        ));

        return $entry;
    }
}
