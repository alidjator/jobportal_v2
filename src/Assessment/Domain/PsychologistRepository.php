<?php

namespace Src\Assessment\Domain;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface PsychologistRepository
{
    public function findById(int $id, bool $forUpdate = false): ?Psychologist;

    public function findByUserId(int $userId): ?Psychologist;

    /**
     * The account ids of these psychologists, keyed by psychologist id; an id without a psychologist is missing.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    public function userIdsOf(array $ids): array;

    /** Inserts a new psychologist or updates an existing one, and returns the stored psychologist (with its id). */
    public function save(Psychologist $psychologist): Psychologist;

    /**
     * @return Page<Psychologist>
     */
    public function pageOfProvider(int $providerId, PageRequest $request): Page;
}
