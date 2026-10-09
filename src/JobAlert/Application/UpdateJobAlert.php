<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\AlertCriteria;
use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Replaces everything a job seeker can set on their own alert. Changing the frequency starts the new rhythm from now. */
final readonly class UpdateJobAlert
{
    public function __construct(private OwnedAlert $owned, private JobAlertRepository $alerts, private Clock $clock, private TransactionManager $transactions) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation the rule of the name, the channels or the criteria
     */
    public function execute(Actor $actor, int $alertId, JobAlertInput $input): JobAlert
    {
        $criteria = AlertCriteria::of($input->criteria);

        return $this->transactions->run(function () use ($actor, $alertId, $input, $criteria): JobAlert {
            $revised = $this->owned->find($actor, $alertId, forUpdate: true)->revise($input->name, $input->frequency, $criteria, $input->email, $input->inApp, $this->clock->now());
            $this->alerts->update($revised);

            return $revised;
        });
    }
}
