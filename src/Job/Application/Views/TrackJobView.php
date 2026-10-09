<?php

namespace Src\Job\Application\Views;

use Src\Job\Application\JakartaDate;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Counts one view of a live job (the client calls it when a person really looks at the page, so the cached page does not hide views). Nothing is counted for a crawler, for a browser that does not name itself, or for a member of the company that owns the job.
 * A signed-in viewer is kept as their account, anyone else as a key that changes every day (`VisitorKeys`). The write goes through the queue.
 */
final readonly class TrackJobView
{
    /** Parts of the user agent of programs that fetch pages without a person behind them. */
    private const string BOT_PATTERN = '/bot|crawl|spider|slurp|preview|fetch|monitor|headless|curl|wget|python-requests|httpclient|go-http|java\//i';

    public function __construct(
        private JobViewRecorder $recorder,
        private VisitorKeys $visitors,
        private JobDirectory $jobs,
        private Clock $clock,
    ) {}

    /** @throws EntityNotFound when the job is not live for the public */
    public function execute(int $jobId, ?Actor $viewer, string $address, string $userAgent): void
    {
        $companyId = $this->jobs->publicCompanyId($jobId) ?? throw EntityNotFound::of('Job', $jobId);

        if (trim($userAgent) === '' || preg_match(self::BOT_PATTERN, $userAgent) === 1) {
            return;
        }

        if ($viewer?->kind === ActorKind::CompanyMember && $viewer->membership?->companyId === $companyId) {
            return;
        }

        $day = JakartaDate::today($this->clock->now())->format('Y-m-d');
        $key = $viewer !== null && $viewer->id !== null && in_array($viewer->kind, [ActorKind::JobSeeker, ActorKind::CompanyMember], true)
            ? 'u'.$viewer->id
            : $this->visitors->forVisitor($address, $userAgent, $day);

        $this->recorder->record($jobId, $key, $day);
    }
}
