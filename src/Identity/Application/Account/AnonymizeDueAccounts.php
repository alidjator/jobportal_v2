<?php

namespace Src\Identity\Application\Account;

use Src\Identity\Domain\Events\UserAnonymized;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;

/**
 * Scheduled job: anonymises every account whose 30-day deletion grace period has ended. Safe to run again at any time:
 * each account is processed once, in its own transaction. A failure stops the run; accounts already done stay done and
 * the next run continues with the rest.
 */
final readonly class AnonymizeDueAccounts
{
    public const int BATCH_SIZE = 100;

    public function __construct(
        private UserRepository $users,
        private UserDataEraser $eraser,
        private FileStorage $files,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @return int the number of accounts anonymised */
    public function execute(): int
    {
        $anonymized = 0;

        do {
            $now = $this->clock->now();
            $due = $this->users->findDueForAnonymization($now, self::BATCH_SIZE);

            foreach ($due as $user) {
                $avatarPath = $user->avatarPath();

                $this->transactions->run(function () use ($user, $now, $avatarPath): void {
                    $user->anonymize($now);
                    $this->users->save($user);
                    $this->eraser->erase((int) $user->id);

                    // Last step: if it fails, the transaction rolls back and the next run retries (deleting a missing file is fine).
                    if ($avatarPath !== null) {
                        $this->files->delete($avatarPath);
                    }
                });

                $this->events->dispatch([new UserAnonymized((int) $user->id, $now)]);
                $anonymized++;
            }
        } while (count($due) === self::BATCH_SIZE);

        return $anonymized;
    }
}
