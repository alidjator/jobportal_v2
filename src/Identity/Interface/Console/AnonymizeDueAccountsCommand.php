<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Account\AnonymizeDueAccounts;

final class AnonymizeDueAccountsCommand extends Command
{
    protected $signature = 'identity:anonymize-due-accounts';

    protected $description = 'Anonymise the accounts whose 30-day deletion grace period has ended';

    public function handle(AnonymizeDueAccounts $anonymizeDueAccounts): int
    {
        $count = $anonymizeDueAccounts->execute();

        $this->info("Anonymised {$count} account(s).");

        return self::SUCCESS;
    }
}
