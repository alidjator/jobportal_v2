<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\AlertCriteria;
use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A job seeker saves a search as an alert, up to the limit an admin set. The alert starts from now: jobs that are already live are not sent. */
final readonly class CreateJobAlert
{
    public function __construct(
        private JobAlertGuard $guard,
        private JobAlertRepository $alerts,
        private Settings $settings,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws BusinessRuleViolation `alert-limit`, or the rule of the name, the channels or the criteria
     */
    public function execute(Actor $actor, JobAlertInput $input): JobAlert
    {
        $userId = $this->guard->userId($actor);
        $alert = JobAlert::create($userId, $input->name, $input->frequency, AlertCriteria::of($input->criteria), $input->email, $input->inApp, $this->clock->now());

        return $this->transactions->run(function () use ($userId, $alert): JobAlert {
            $limit = $this->settings->integer(JobAlertSettings::MAX_ALERTS_PER_USER);

            if ($this->alerts->countOfUser($userId, forUpdate: true) >= $limit) {
                throw new BusinessRuleViolation("You can have at most {$limit} job alerts. Delete one first.", 'alert-limit');
            }

            return $this->alerts->add($alert);
        });
    }
}
