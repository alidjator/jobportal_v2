<?php

namespace Src\Job\Application\Views;

/** Removes what the view log holds about an account that was anonymised: its raw rows. The daily counts hold no viewer and stay. Safe to run again. */
final readonly class EraseJobViews
{
    public function __construct(private JobViewLog $log) {}

    public function execute(int $userId): void
    {
        $this->log->eraseViewer($userId);
    }
}
