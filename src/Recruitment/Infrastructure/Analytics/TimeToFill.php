<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\Job\PublishedJob;
use Src\Shared\Application\Job\PublishedJobs;

/**
 * How long jobs take to fill. A job is filled at the moment of its N-th hire (N = its vacancies, one when none are stated) among the applications hired now; it counts
 * whatever its status is, and a job with fewer hires than that is not filled. The time runs from the first moment the job went live. A job never published is ignored.
 */
final readonly class TimeToFill
{
    public function __construct(private HireTimes $hires, private PublishedJobs $jobs) {}

    /**
     * The jobs filled from `fromUtc` (included) to `toUtc` (not included), as UTC `Y-m-d H:i:s`; for one company, or every company when null. Oldest fill first.
     *
     * @return list<FilledJob>
     */
    public function filled(?int $companyId, string $fromUtc, string $toUtc): array
    {
        $hires = $this->hires->of($companyId);
        $filled = [];

        foreach ($this->jobs->among(array_keys($hires)) as $job) {
            $moments = $hires[$job->id];

            // An imported job went live and was filled before the platform: it is not a filled job here.
            if ($job->imported || count($moments) < $job->positions()) {
                continue;
            }

            $moment = $moments[$job->positions() - 1];

            if ($moment < $fromUtc || $moment >= $toUtc) {
                continue;
            }

            $at = new DateTimeImmutable($moment, new DateTimeZone('UTC'));
            $filled[] = new FilledJob($job, $at, self::days($job, $at), count($moments));
        }

        usort($filled, fn (FilledJob $a, FilledJob $b): int => [$a->filledAt, $a->job->id] <=> [$b->filledAt, $b->job->id]);

        return $filled;
    }

    /**
     * The live jobs of the company that are not filled yet, the longest open first.
     *
     * @return list<OpenJob>
     */
    public function open(int $companyId, DateTimeImmutable $now): array
    {
        $hires = $this->hires->of($companyId);
        $open = [];

        foreach ($this->jobs->liveOfCompany($companyId) as $job) {
            $count = count($hires[$job->id] ?? []);

            if (! $job->imported && $count < $job->positions()) {
                $open[] = new OpenJob($job, self::days($job, $now), $count);
            }
        }

        usort($open, fn (OpenJob $a, OpenJob $b): int => [$b->daysOpen, $a->job->id] <=> [$a->daysOpen, $b->job->id]);

        return $open;
    }

    /** Days with one decimal from the publication to a moment; a moment before the publication (imported data) counts as zero. */
    private static function days(PublishedJob $job, DateTimeImmutable $at): float
    {
        return round(max(0, $at->getTimestamp() - $job->publishedAt->getTimestamp()) / 86400, 1);
    }
}
