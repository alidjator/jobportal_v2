<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Admin\CreateAdminRole;
use Src\Shared\Domain\Exceptions\DomainException;

/** The operator's way to create a role with limited rights; give it to an administrator with identity:set-admin-role. */
final class CreateAdminRoleCommand extends Command
{
    use NamesTheOperator;

    protected $signature = 'identity:create-role {name : Role name (lowercase letters, digits, underscores)} {--permission=* : A permission the role holds (repeat the option)}';

    protected $description = 'Create an admin role with the given permissions';

    public function handle(CreateAdminRole $create): int
    {
        try {
            $permissions = $create->execute((string) $this->argument('name'), $this->permissions(), $this->operator());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Role '.$this->argument('name').' created with: '.implode(', ', $permissions).'.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function permissions(): array
    {
        return array_values(array_map('strval', (array) $this->option('permission')));
    }
}
