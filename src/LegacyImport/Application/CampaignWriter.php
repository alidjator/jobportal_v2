<?php

namespace Src\LegacyImport\Application;

/** Writes campaigns into the table of the Campaign context (the documented exception of this context). */
interface CampaignWriter
{
    /** Whether a campaign already has this code (compared in lower case). */
    public function codeTaken(string $code): bool;

    /**
     * Inserts a campaign, active, created by a company member (`$creatorUserId`). The code is already lower case. Dates are `Y-m-d` or null; times are `Y-m-d H:i:s` UTC.
     *
     * @return int the id of the new campaign
     */
    public function insert(int $companyId, string $code, string $name, string $type, ?string $startsOn, ?string $endsOn, int $creatorUserId, string $createdAt, string $updatedAt): int;
}
