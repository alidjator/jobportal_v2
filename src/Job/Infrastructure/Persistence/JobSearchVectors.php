<?php

namespace Src\Job\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\References\TagRegistry;

/**
 * Keeps `job_jobs.search_vector` (the full text index of the job search) true: title weight A, tag names B, the company name C and the description D with its
 * Markdown marks removed, all unaccented and in the `simple` configuration. Refreshed when a job is saved and when its company is renamed.
 */
final readonly class JobSearchVectors
{
    private const int BATCH = 200;

    public function __construct(
        private ConnectionInterface $connection,
        private CompanyDirectory $companies,
        private TagRegistry $tags,
    ) {}

    public function refresh(int $jobId): void
    {
        $row = $this->connection->table('job_jobs')->where('id', $jobId)->first(['id', 'title', 'description', 'company_id']);

        if ($row !== null) {
            $this->write((int) $row->id, (string) $row->title, $row->description === null ? '' : (string) $row->description, $this->companyName((int) $row->company_id));
        }
    }

    /** Every job of the company, in batches, after the company took a new name. */
    public function refreshCompany(int $companyId): void
    {
        $name = $this->companyName($companyId);
        $afterId = 0;

        do {
            $rows = $this->connection->table('job_jobs')->where('company_id', $companyId)->where('id', '>', $afterId)->orderBy('id')->limit(self::BATCH)->get(['id', 'title', 'description']);

            foreach ($rows as $row) {
                $this->write((int) $row->id, (string) $row->title, $row->description === null ? '' : (string) $row->description, $name);
                $afterId = (int) $row->id;
            }
        } while ($rows->count() === self::BATCH);
    }

    private function companyName(int $companyId): string
    {
        return $this->companies->summaries([$companyId])[$companyId]->name ?? '';
    }

    private function write(int $jobId, string $title, string $description, string $companyName): void
    {
        $tagIds = array_map('intval', $this->connection->table('job_job_tags')->where('job_id', $jobId)->pluck('tag_id')->all());
        $tagNames = implode(' ', $this->tags->names($tagIds));
        $plain = (string) preg_replace('/[#*_`>~\[\]()!|-]+/', ' ', $description);

        $vector = $this->connection->selectOne(
            "SELECT (setweight(to_tsvector('simple', immutable_unaccent(?)), 'A') || setweight(to_tsvector('simple', immutable_unaccent(?)), 'B') || setweight(to_tsvector('simple', immutable_unaccent(?)), 'C') || setweight(to_tsvector('simple', immutable_unaccent(?)), 'D'))::text AS vector",
            [$title, $tagNames, $companyName, $plain],
        );

        $this->connection->table('job_jobs')->where('id', $jobId)->update(['search_vector' => $vector->vector]);
    }
}
