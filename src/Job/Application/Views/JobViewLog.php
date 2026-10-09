<?php

namespace Src\Job\Application\Views;

/** The raw log of counted job views (`job_views`) and the daily counts folded out of it (`job_view_daily`). */
interface JobViewLog
{
    /** Adds the view; a second view by the same viewer of the same job on the same day is ignored. */
    public function add(int $jobId, string $viewerKey, string $day): void;

    /**
     * Folds the raw rows of days before `$before` into the daily counts and deletes them, in one transaction. Running it again changes nothing more.
     *
     * @param  string  $before  a Jakarta calendar day, `Y-m-d`
     * @return int how many raw rows were removed
     */
    public function foldBefore(string $before): int;

    /**
     * The counted views of one job per Jakarta calendar day, from a day on.
     *
     * @param  string  $from  a Jakarta calendar day, `Y-m-d`, included
     * @return array<string, int> `Y-m-d` => views, quiet days missing
     */
    public function dailyViews(int $jobId, string $from): array;

    /** Deletes the raw rows of a signed-in viewer; the daily counts hold no viewer. @return int how many rows were removed */
    public function eraseViewer(int $userId): int;
}
