<?php

namespace Src\Job\Application\Views;

use Src\Shared\Application\Accounts\ErasureStep;

/** Removes what the view log holds about an account that was anonymised: its raw rows. The daily counts hold no viewer and stay. Safe to run again. */
final readonly class EraseJobViews implements ErasureStep
{
    public function __construct(private JobViewLog $log) {}

    public function name(): string
    {
        return 'job-views';
    }

    public function execute(int $userId): void
    {
        $this->log->eraseViewer($userId);
    }
}
