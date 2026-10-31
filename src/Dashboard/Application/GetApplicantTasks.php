<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Tasks\ApplicantTask;
use Src\Shared\Application\Tasks\ApplicantTaskSources;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The to-do list of a job seeker (`docs/design/phase-8-candidate-experience.md` section 3): what waits for them in every context, the ones with a due time first (the nearest first), then the others, newest first;
 * at most {@see self::LIMIT} items. Each context fills it through a source; this class merges, sorts and names the jobs and companies. It is read live (not cached) and only reads.
 */
final readonly class GetApplicantTasks
{
    public const int LIMIT = 100;

    public function __construct(private ApplicantTaskSources $sources, private JobDirectory $jobs, private CompanyDirectory $companies, private Clock $clock) {}

    /**
     * @return array{items: list<ApplicantTaskView>, total: int}
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor): array
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker has this list.', 'job-seeker-only');
        }

        return $this->forUser($actor->id);
    }

    /** @return array{items: list<ApplicantTaskView>, total: int} `total` counts the items before the list is cut at {@see self::LIMIT} */
    public function forUser(int $userId): array
    {
        $now = $this->clock->now();
        $tasks = [];

        foreach ($this->sources->all() as $source) {
            array_push($tasks, ...$source->tasks($userId, $now, self::LIMIT));
        }

        usort($tasks, static function (ApplicantTask $a, ApplicantTask $b): int {
            if ($a->dueAt !== null && $b->dueAt !== null) {
                return [$a->dueAt, $a->key, $a->subjectId] <=> [$b->dueAt, $b->key, $b->subjectId];
            }

            if ($a->dueAt !== null || $b->dueAt !== null) {
                return $a->dueAt !== null ? -1 : 1;
            }

            return [$b->createdAt, $a->key, $a->subjectId] <=> [$a->createdAt, $b->key, $b->subjectId];
        });

        $kept = array_slice($tasks, 0, self::LIMIT);
        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (ApplicantTask $task): int => $task->jobId, $kept))));
        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn (ApplicantTask $task): int => $task->companyId, $kept))));

        return [
            'items' => array_map(static fn (ApplicantTask $task): ApplicantTaskView => new ApplicantTaskView(
                $task->key, $task->subjectId, $task->applicationId, $task->jobId, $titles[$task->jobId] ?? null, ($companies[$task->companyId] ?? null)?->name, $task->dueAt, $task->createdAt,
            ), $kept),
            'total' => count($tasks),
        ];
    }
}
