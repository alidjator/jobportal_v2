<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\Events\JobFilled;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Recruitment\JobHires;

/**
 * Tells whether a hire has just filled its job (`docs/design/phase-8-hiring.md` section 2): the application entered `hired` and now the job's hires equal its
 * vacancies, so a later hire, or a job without vacancies, gives no prompt. Runs in the transaction of the hire, after the application was saved; it locks the job
 * first, so two hires at once count one after the other and exactly one of them crosses the line.
 */
final readonly class WatchJobFill
{
    public function __construct(private JobDirectory $jobs, private JobHires $hires) {}

    public function afterMove(Application $before, Application $after, DateTimeImmutable $now): ?JobFilled
    {
        if ($after->stageType !== StageType::Hired || $before->stageType === StageType::Hired) {
            return null;
        }

        $this->jobs->lock($before->jobId);

        $vacancies = $this->jobs->vacancies($before->jobId);

        if ($vacancies === null) {
            return null;
        }

        $hires = $this->hires->countPerJob([$before->jobId])[$before->jobId] ?? 0;

        return $hires === $vacancies ? new JobFilled($before->jobId, $before->companyId, $hires, $vacancies, $now) : null;
    }
}
