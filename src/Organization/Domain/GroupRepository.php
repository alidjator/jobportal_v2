<?php

namespace Src\Organization\Domain;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface GroupRepository
{
    public function find(int $id): ?Group;

    /** Locks the row until the transaction ends, so a suspension and a restore of one group never overlap. */
    public function lock(int $id): void;

    public function create(Group $group): Group;

    public function save(Group $group): void;

    public function nameTaken(string $name, ?int $exceptId = null): bool;

    /** @return Page<Group> */
    public function page(PageRequest $request): Page;
}
