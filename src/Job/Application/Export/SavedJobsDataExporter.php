<?php

namespace Src\Job\Application\Export;

use DateTimeInterface;
use Src\Job\Domain\SavedJob;
use Src\Job\Domain\SavedJobRepository;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;

/** The Job part of the personal data export: the jobs the job seeker saved. */
final readonly class SavedJobsDataExporter implements PersonalDataExporter
{
    public function __construct(private SavedJobRepository $saved) {}

    public function key(): string
    {
        return 'saved_jobs';
    }

    public function export(int $userId): PersonalDataSection
    {
        $jobs = [];

        for ($page = 1; ; $page++) {
            $result = $this->saved->ofUser($userId, new PageRequest($page, PageRequest::MAX_PER_PAGE));
            array_push($jobs, ...$result->items);

            if (count($result->items) < PageRequest::MAX_PER_PAGE) {
                break;
            }
        }

        return new PersonalDataSection(array_map(static fn (SavedJob $job): array => ['job' => $job->title, 'saved_at' => $job->savedAt->format(DateTimeInterface::ATOM)], $jobs));
    }
}
