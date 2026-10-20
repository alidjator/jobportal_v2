<?php

namespace Src\LegacyImport\Application;

/** Writes job alerts into the table of the JobAlert context (the documented exception of this context). */
interface JobAlertWriter
{
    /**
     * Inserts a daily alert with the email channel on and the in-app channel on. `$now` is `Y-m-d H:i:s` UTC and is the cursor, so only jobs that go live later are sent.
     *
     * @param  array<string, list<int>>  $criteria  the search filters by name (here only `role_id`)
     * @return int the id of the new alert
     */
    public function insert(int $userId, string $name, array $criteria, bool $paused, string $now): int;
}
