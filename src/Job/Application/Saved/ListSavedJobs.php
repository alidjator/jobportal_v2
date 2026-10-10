<?php

namespace Src\Job\Application\Saved;

use Src\Job\Application\Browse\JobSearchCriteria;
use Src\Job\Application\Browse\JobSearchQueryBuilder;
use Src\Job\Application\Browse\JobSearchReader;
use Src\Job\Application\Browse\PublicJobListItem;
use Src\Job\Application\Browse\PublicJobListItems;
use Src\Job\Domain\SavedJob;
use Src\Job\Domain\SavedJobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The job seeker's saved jobs, the most recently saved first. A job that is still live comes as the row of the public search; one that is not keeps its place
 * as an entry without a row, so the job seeker sees that it is gone instead of it vanishing from the list.
 */
final readonly class ListSavedJobs
{
    public function __construct(
        private SavedJobAccess $access,
        private SavedJobRepository $saved,
        private JobSearchReader $reader,
        private JobSearchQueryBuilder $queries,
        private PublicJobListItems $items,
    ) {}

    /**
     * @return Page<SavedJobEntry>
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor, PageRequest $request, string $language): Page
    {
        $page = $this->saved->ofUser($this->access->jobSeeker($actor), $request);

        $hits = $this->reader->hitsOf($this->queries->build(new JobSearchCriteria), array_map(static fn (SavedJob $job): int => $job->jobId, $page->items));

        /** @var array<int, PublicJobListItem> $live */
        $live = [];

        foreach ($this->items->of($hits, $language) as $item) {
            $live[$item->id] = $item->withSaved(true);
        }

        return new Page(
            array_map(static fn (SavedJob $job): SavedJobEntry => new SavedJobEntry($job->jobId, $job->title, $job->savedAt, $live[$job->jobId] ?? null), $page->items),
            $page->total,
            $page->request,
        );
    }
}
