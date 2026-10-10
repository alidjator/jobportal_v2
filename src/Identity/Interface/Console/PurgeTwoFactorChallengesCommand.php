<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\TwoFactor\PurgeTwoFactorChallenges;

final class PurgeTwoFactorChallengesCommand extends Command
{
    protected $signature = 'identity:purge-two-factor-challenges';

    protected $description = 'Delete the expired or used admin sign-in challenges';

    public function handle(PurgeTwoFactorChallenges $purge): int
    {
        $count = $purge->execute();

        $this->info("Deleted {$count} challenge(s).");

        return self::SUCCESS;
    }
}
