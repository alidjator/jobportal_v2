<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Erasure\ReconcileErasure;

final class ReconcileErasureCommand extends Command
{
    protected $signature = 'identity:reconcile-erasure';

    protected $description = 'Run again the erasure steps that did not finish for accounts anonymised in the last 90 days';

    public function handle(ReconcileErasure $reconcile): int
    {
        $result = $reconcile->execute();

        $this->info("Ran {$result['repaired']} erasure step(s) again; {$result['failed']} still failing.");

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
