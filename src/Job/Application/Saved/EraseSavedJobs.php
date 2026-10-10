<?php

namespace Src\Job\Application\Saved;

use Src\Job\Domain\SavedJobRepository;

/** Deletes the saved jobs of a user whose account was anonymised. Running it again does nothing. */
final readonly class EraseSavedJobs
{
    public function __construct(private SavedJobRepository $saved) {}

    public function execute(int $userId): void
    {
        $this->saved->deleteOfUser($userId);
    }
}
