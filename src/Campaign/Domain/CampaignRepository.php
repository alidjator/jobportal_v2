<?php

namespace Src\Campaign\Domain;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

interface CampaignRepository
{
    /**
     * Stores a new campaign and returns it with its id.
     *
     * @throws StateConflict `campaign-code-taken`
     */
    public function add(Campaign $campaign): Campaign;

    public function update(Campaign $campaign): void;

    public function delete(int $campaignId): void;

    public function find(int $campaignId, bool $forUpdate = false): ?Campaign;

    /** @param  string  $code  lower case */
    public function findByCode(string $code): ?Campaign;

    /** @return Page<Campaign> */
    public function page(CampaignQuery $query, PageRequest $request): Page;
}
