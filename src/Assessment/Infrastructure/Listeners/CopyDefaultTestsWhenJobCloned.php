<?php

namespace Src\Assessment\Infrastructure\Listeners;

use Src\Assessment\Domain\JobStageTestRepository;
use Src\Job\Domain\Events\JobCloned;

/** A cloned job keeps the default tests of the job it was copied from (`docs/design/phase-8-assessment-automation.md` section 1). The member who cloned the job is the one who last changed them. */
final readonly class CopyDefaultTestsWhenJobCloned
{
    public function __construct(private JobStageTestRepository $defaults) {}

    public function handle(JobCloned $event): void
    {
        $this->defaults->copy($event->sourceJobId, $event->newJobId, $event->clonedByUserId);
    }
}
