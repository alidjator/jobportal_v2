<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Admin\DeleteAdminRole;
use Src\Shared\Domain\Exceptions\DomainException;

final class DeleteAdminRoleCommand extends Command
{
    use NamesTheOperator;

    protected $signature = 'identity:delete-role {name : Role name}';

    protected $description = 'Delete an admin role that no administrator holds';

    public function handle(DeleteAdminRole $delete): int
    {
        $name = (string) $this->argument('name');

        if (! $this->confirm("Delete the role {$name}?")) {
            return self::FAILURE;
        }

        try {
            $delete->execute($name, $this->operator());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Role {$name} deleted.");

        return self::SUCCESS;
    }
}
