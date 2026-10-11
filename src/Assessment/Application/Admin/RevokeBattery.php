<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\CompanyBatteryRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** An admin takes a battery away from a company. Revoking one the company does not have changes nothing. */
final readonly class RevokeBattery
{
    public function __construct(private CompanyBatteryRepository $grants) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     */
    public function execute(Actor $actor, int $companyId, int $testId): void
    {
        AdminOnly::assert($actor, 'revoke a battery');

        $this->grants->revoke($companyId, $testId);
    }
}
