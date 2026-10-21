<?php

namespace Src\Job\Application\Saved;

use Src\Job\Application\Browse\JobSearchCriteria;
use Src\Job\Application\Browse\JobSearchQueryBuilder;
use Src\Job\Application\Browse\JobSearchReader;
use Src\Job\Domain\SavedJobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A job seeker saves a live job (one the public page shows). Saving a job again is not an error and changes nothing. A job seeker keeps at most 200 saved jobs.
 */
final readonly class SaveJob
{
    public const int MAX_SAVED = 200;

    public function __construct(
        private SavedJobAccess $access,
        private JobSearchReader $reader,
        private JobSearchQueryBuilder $queries,
        private SavedJobRepository $saved,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @return bool whether the job was saved now (false when it was already saved)
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound the job is not live, or does not exist
     * @throws BusinessRuleViolation `saved-jobs-limit`
     */
    public function execute(Actor $actor, int $jobId): bool
    {
        $userId = $this->access->jobSeeker($actor);

        if ($this->reader->hitsOf($this->queries->build(new JobSearchCriteria), [$jobId]) === []) {
            throw EntityNotFound::of('Job', $jobId);
        }

        return $this->transactions->run(function () use ($userId, $jobId): bool {
            $new = $this->saved->save($userId, $jobId, $this->clock->now());

            // Over the limit: throwing rolls the new row back.
            if ($new && $this->saved->count($userId) > self::MAX_SAVED) {
                throw new BusinessRuleViolation('You can save at most :max jobs. Remove one first.', 'saved-jobs-limit', parameters: ['max' => self::MAX_SAVED]);
            }

            return $new;
        });
    }
}
