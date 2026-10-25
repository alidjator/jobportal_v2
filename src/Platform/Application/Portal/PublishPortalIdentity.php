<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Portal\PortalOwner;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A platform admin makes the identity draft live, after the rules of `PortalIdentityRules`. The draft is deleted and the change audited with the values before and after. */
final readonly class PublishPortalIdentity
{
    public function __construct(
        private Portals $portals,
        private PortalIdentityRules $rules,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @throws BusinessRuleViolation `portal-draft-missing` or a code of {@see PortalIdentityRules::check()} */
    public function execute(int $adminId, ?string $ipAddress, ?string $userAgent): PortalIdentity
    {
        $draft = $this->portals->draft(PortalOwner::platform())
            ?? throw new BusinessRuleViolation('There is no draft to publish.', 'portal-draft-missing');
        $new = PortalIdentity::fromArray($draft->payload);

        $this->rules->check($new);

        $before = $this->portals->identity() ?? PortalIdentity::marketplace();
        $now = $this->clock->now();

        $this->transactions->run(function () use ($adminId, $new, $before, $ipAddress, $userAgent, $now): void {
            $this->portals->saveIdentity($new, $adminId, $now);
            $this->portals->deleteDraft(PortalOwner::platform());

            if (! $new->equals($before)) {
                $this->audit->record(new AuditEntry(
                    AuditActorType::Admin, $adminId, AuditAction::PORTAL_IDENTITY_PUBLISHED, 'portal_identity', null,
                    ['before' => $before->toArray(), 'after' => $new->toArray()], $ipAddress, $userAgent, $now,
                ));
            }
        });

        return $new;
    }
}
