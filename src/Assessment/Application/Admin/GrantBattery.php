<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Domain\CompanyBatteryRepository;
use Src\Assessment\Domain\TestKind;
use Src\Assessment\Domain\TestRepository;
use Src\Assessment\Domain\TestStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** An admin lets a company use a published psychological battery. Granting it again changes nothing. */
final readonly class GrantBattery
{
    public function __construct(
        private TestRepository $tests,
        private CompanyBatteryRepository $grants,
        private CompanyDirectory $companies,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound the company or the battery
     * @throws StateConflict `battery-not-published`
     */
    public function execute(Actor $actor, int $companyId, int $testId): void
    {
        AdminOnly::assert($actor, 'grant a battery');

        if (! $this->companies->exists($companyId)) {
            throw EntityNotFound::of('Company', $companyId);
        }

        $test = $this->tests->find($testId);

        if ($test === null || $test->kind() !== TestKind::Psychological) {
            throw EntityNotFound::of('Battery', $testId);
        }

        if ($test->status() !== TestStatus::Published) {
            throw new StateConflict('Only a published battery can be granted.', 'battery-not-published');
        }

        $this->grants->grant($companyId, $testId, (int) $actor->id, $this->clock->now());
    }
}
