<?php

namespace Src\Training\Infrastructure;

use Illuminate\Contracts\Cache\Repository as Cache;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Training\Application\TrainingCatalog;

/**
 * Keeps what Moodle answered. The catalog is fresh for 15 minutes; a second copy lives for a day and is served when Moodle fails, so a Moodle outage does not take
 * the training page down. The courses of one account are kept 5 minutes (a page refresh does not call Moodle again) and have no second copy: stale progress is
 * worse than an error. The cache key holds a hash of the email, never the email.
 */
final readonly class CachedTrainingCatalog implements TrainingCatalog
{
    private const string COURSES_KEY = 'training:courses';

    private const string STALE_COURSES_KEY = 'training:courses:stale';

    private const int COURSES_SECONDS = 900;

    private const int STALE_COURSES_SECONDS = 86400;

    private const int ENROLLMENTS_SECONDS = 300;

    public function __construct(private MoodleTrainingCatalog $moodle, private Cache $cache) {}

    public function courses(): array
    {
        $fresh = $this->cache->get(self::COURSES_KEY);

        if (is_array($fresh)) {
            return $fresh;
        }

        try {
            $courses = $this->moodle->courses();
        } catch (ExternalServiceUnavailable $e) {
            $stale = $this->cache->get(self::STALE_COURSES_KEY);

            return is_array($stale) ? $stale : throw $e;
        }

        $this->cache->put(self::COURSES_KEY, $courses, self::COURSES_SECONDS);
        $this->cache->put(self::STALE_COURSES_KEY, $courses, self::STALE_COURSES_SECONDS);

        return $courses;
    }

    public function enrollmentsOf(string $email): array
    {
        return $this->cache->remember('training:enrollments:'.hash('sha256', mb_strtolower($email)), self::ENROLLMENTS_SECONDS, fn (): array => $this->moodle->enrollmentsOf($email));
    }
}
