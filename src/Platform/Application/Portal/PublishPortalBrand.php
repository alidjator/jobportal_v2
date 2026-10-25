<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Portal\PortalBrandValues;
use Src\Shared\Application\Portal\PortalOwner;
use Src\Shared\Application\Portal\PortalOwnerType;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Makes the brand draft of a group or company live, deletes the draft and audits the values before and after (nothing is audited when the brand does not change). */
final readonly class PublishPortalBrand
{
    public function __construct(
        private Portals $portals,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @throws BusinessRuleViolation `portal-draft-missing` */
    public function execute(PortalOwner $owner, int $userId, ?string $ipAddress, ?string $userAgent): void
    {
        $draft = $this->portals->draft($owner)
            ?? throw new BusinessRuleViolation('There is no draft to publish.', 'portal-draft-missing');
        $new = PortalBrandValues::fromArray($draft->payload);
        $before = $this->portals->brand($owner) ?? PortalBrandValues::default();
        $now = $this->clock->now();

        $this->transactions->run(function () use ($owner, $userId, $new, $before, $ipAddress, $userAgent, $now): void {
            $this->portals->saveBrand($owner, $new, $userId, $now);
            $this->portals->deleteDraft($owner);

            if (! $new->equals($before)) {
                $this->audit->record(new AuditEntry(
                    AuditActorType::User, $userId, AuditAction::PORTAL_BRAND_PUBLISHED, 'portal_brand', $owner->id,
                    ['owner_type' => $owner->type->value, 'before' => $before->toArray(), 'after' => $new->toArray()], $ipAddress, $userAgent, $now,
                    $owner->type === PortalOwnerType::Company ? $owner->id : null,
                    $owner->type === PortalOwnerType::Group ? $owner->id : null,
                ));
            }
        });
    }
}
