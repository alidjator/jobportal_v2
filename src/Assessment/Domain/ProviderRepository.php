<?php

namespace Src\Assessment\Domain;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface ProviderRepository
{
    public function findById(int $id, bool $forUpdate = false): ?Provider;

    /** Inserts a new provider or updates an existing one, and returns the stored provider (with its id). */
    public function save(Provider $provider): Provider;

    /** True when another provider already uses the name, compared case-insensitively. */
    public function nameIsTaken(string $name, ?int $exceptId = null): bool;

    /**
     * @return Page<Provider>
     */
    public function page(?string $search, ?ProviderStatus $status, PageRequest $request): Page;
}
