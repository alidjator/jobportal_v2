<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Admin\ReactivateAdmin;
use Src\Shared\Domain\Exceptions\DomainException;

final class ReactivateAdminCommand extends Command
{
    use NamesTheOperator;

    protected $signature = 'identity:reactivate-admin {email : Email address of the administrator}';

    protected $description = 'Reactivate a suspended administrator';

    public function handle(ReactivateAdmin $reactivate): int
    {
        try {
            $adminId = $reactivate->execute((string) $this->argument('email'), $this->operator());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Administrator {$adminId} reactivated.");

        return self::SUCCESS;
    }
}
