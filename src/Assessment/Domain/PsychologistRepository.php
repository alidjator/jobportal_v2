<?php

namespace Src\Assessment\Domain;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface PsychologistRepository
{
    public function findById(int $id, bool $forUpdate = false): ?Psychologist;

    public function findByUserId(int $userId): ?Psychologist;

    /** Inserts a new psychologist or updates an existing one, and returns the stored psychologist (with its id). */
    public function save(Psychologist $psychologist): Psychologist;

    /**
     * @return Page<Psychologist>
     */
    public function pageOfProvider(int $providerId, PageRequest $request): Page;
}
