<?php

namespace Src\Job\Application\Saved;

use Src\Job\Domain\SavedJobRepository;
use Src\Shared\Application\Accounts\ErasureStep;

/** Deletes the saved jobs of a user whose account was anonymised. Running it again does nothing. */
final readonly class EraseSavedJobs implements ErasureStep
{
    public function __construct(private SavedJobRepository $saved) {}

    public function name(): string
    {
        return 'saved-jobs';
    }

    public function execute(int $userId): void
    {
        $this->saved->deleteOfUser($userId);
    }
}
