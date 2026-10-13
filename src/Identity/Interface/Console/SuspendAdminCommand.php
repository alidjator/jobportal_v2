<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Admin\SuspendAdmin;
use Src\Shared\Domain\Exceptions\DomainException;

final class SuspendAdminCommand extends Command
{
    use NamesTheOperator;

    protected $signature = 'identity:suspend-admin {email : Email address of the administrator}';

    protected $description = 'Suspend an administrator: every session ends and sign-in is refused until reactivation';

    public function handle(SuspendAdmin $suspend): int
    {
        $email = (string) $this->argument('email');

        if (! $this->confirm("Suspend {$email} and end every session?")) {
            return self::FAILURE;
        }

        try {
            $adminId = $suspend->execute($email, $this->operator());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Administrator {$adminId} suspended.");

        return self::SUCCESS;
    }
}
