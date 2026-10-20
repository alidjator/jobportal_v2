<?php

namespace Src\LegacyImport\Application;

/** Writes saved jobs into the table of the Job context (the documented exception of this context). */
interface SavedJobWriter
{
    /**
     * Which of these (new) job ids are not live now: such a job is kept in the list and shown as unavailable, as the feature already does.
     *
     * @param  list<int>  $jobIds
     * @return list<int>
     */
    public function notLive(array $jobIds): array;

    /**
     * Saves the job for the user, `$savedAt` as `Y-m-d H:i:s` UTC.
     *
     * @return int|null the id of the new row, or null when the user had saved that job already
     */
    public function insert(int $userId, int $jobId, string $savedAt): ?int;
}
