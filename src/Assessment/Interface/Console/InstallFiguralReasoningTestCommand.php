<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\AssessmentPermissions;
use Src\Assessment\Application\Library\Figural\InstallFiguralReasoningTest;
use Src\Shared\Application\Accounts\AdminAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\DomainException;

/** The operator's way to put the figural reasoning test into the system library (`docs/design/phase-8-figural-reasoning-test.md`), published, in the name of an admin who manages the library. */
final class InstallFiguralReasoningTestCommand extends Command
{
    protected $signature = 'assessment:install-figural-test
        {--admin= : Id of the platform admin the test is written by (needs assessment_library.manage; see identity:list-admins)}';

    protected $description = 'Write and publish the 40-item figural reasoning test in the system library';

    public function handle(InstallFiguralReasoningTest $install, AdminAccounts $admins): int
    {
        $adminId = (int) ($this->option('admin') ?: $this->ask('Admin id'));

        if ($adminId < 1 || ! $admins->holds($adminId, AssessmentPermissions::LIBRARY_MANAGE)) {
            $this->error('The admin does not exist, is not active, or does not hold '.AssessmentPermissions::LIBRARY_MANAGE.'.');

            return self::FAILURE;
        }

        try {
            $testId = $install->execute(Actor::admin($adminId));
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Figural reasoning test published in the library (test id {$testId}).");

        return self::SUCCESS;
    }
}
