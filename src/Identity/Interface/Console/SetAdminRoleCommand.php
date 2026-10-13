<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Admin\ChangeAdminRole;
use Src\Shared\Domain\Exceptions\DomainException;

final class SetAdminRoleCommand extends Command
{
    use NamesTheOperator;

    protected $signature = 'identity:set-admin-role {email : Email address of the administrator} {role : The role they hold from now on}';

    protected $description = 'Give an administrator another role (an administrator holds one role)';

    public function handle(ChangeAdminRole $change): int
    {
        $email = (string) $this->argument('email');
        $role = (string) $this->argument('role');

        if (! $this->confirm("Give {$email} the role {$role}?")) {
            return self::FAILURE;
        }

        try {
            $adminId = $change->execute($email, $role, $this->operator());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Administrator {$adminId} now holds the role {$role}.");

        return self::SUCCESS;
    }
}
