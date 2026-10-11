<?php

namespace Src\Identity\Application\Erasure;

use Psr\Log\LoggerInterface;
use Src\Shared\Application\Accounts\ErasureRunner;
use Src\Shared\Application\Accounts\ErasureSteps;
use Src\Shared\Application\Clock;
use Throwable;

/**
 * Scheduled safety net for the erasure of personal data. The contexts erase through queued listeners; when one gives up (or the event was lost) the account
 * is anonymised but its data stays in that context. This finds the accounts anonymised in the last 90 days that have no receipt for some step and runs
 * that step again, which is safe because every step can run again. A step that fails again is logged at error level and tried at the next run.
 */
final readonly class ReconcileErasure
{
    public const int BATCH_SIZE = 100;

    public const int WINDOW_DAYS = 90;

    /** Recent accounts are left to the listeners, which retry for about six minutes. */
    public const int GRACE_MINUTES = 60;

    public function __construct(
        private ErasureLedger $ledger,
        private ErasureSteps $steps,
        private ErasureRunner $runner,
        private LoggerInterface $logger,
        private Clock $clock,
    ) {}

    /** @return array{repaired: int, failed: int} the number of steps run again and, of those, how many failed */
    public function execute(): array
    {
        $now = $this->clock->now();
        $from = $now->modify('-'.self::WINDOW_DAYS.' days');
        $to = $now->modify('-'.self::GRACE_MINUTES.' minutes');
        $steps = $this->steps->all();
        $repaired = 0;
        $failed = 0;
        $afterId = 0;

        do {
            $userIds = $this->ledger->anonymizedBetween($from, $to, $afterId, self::BATCH_SIZE);
            $finished = $this->ledger->finishedSteps($userIds);

            foreach ($userIds as $userId) {
                foreach ($steps as $step) {
                    if (in_array($step->name(), $finished[$userId] ?? [], true)) {
                        continue;
                    }

                    try {
                        $this->runner->run($step, $userId);
                        $repaired++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->logger->error('Erasure step still failing for an anonymised account.', [
                            'step' => $step->name(),
                            'user_id' => $userId,
                            'exception' => $e,
                        ]);
                    }
                }
            }

            $afterId = $userIds === [] ? $afterId : $userIds[array_key_last($userIds)];
        } while (count($userIds) === self::BATCH_SIZE);

        return ['repaired' => $repaired, 'failed' => $failed];
    }
}
