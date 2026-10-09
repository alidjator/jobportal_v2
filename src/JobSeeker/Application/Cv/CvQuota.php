<?php

namespace Src\JobSeeker\Application\Cv;

use Src\JobSeeker\Application\JobSeekerSettings;
use Src\JobSeeker\Domain\CvGenerationRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The hourly limit on the CVs a job seeker has generated (downloads and applications together). The limit is read before the CV is made and one record is
 * added after it was, so two requests at the very same moment may overshoot it by one, and a CV that failed uses no slot.
 */
final readonly class CvQuota
{
    private const string WINDOW = '-1 hour';

    /** Records older than this are of no use any more and are deleted when a new one is added. */
    private const string KEEP = '-1 day';

    public function __construct(private CvGenerationRepository $generations, private Settings $settings, private Clock $clock) {}

    /** @throws TooManyAttempts `cv-limit` */
    public function assertRoom(int $userId): void
    {
        $limit = $this->settings->integer(JobSeekerSettings::CV_GENERATIONS_PER_HOUR);

        if ($this->generations->countSince($userId, $this->clock->now()->modify(self::WINDOW)) >= $limit) {
            throw new TooManyAttempts('You have had too many CVs generated this hour. Try again later.', 600, 'cv-limit');
        }
    }

    public function record(int $userId): void
    {
        $now = $this->clock->now();

        $this->generations->record($userId, $now, $now->modify(self::KEEP));
    }
}
