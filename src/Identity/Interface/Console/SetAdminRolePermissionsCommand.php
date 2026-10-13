<?php

namespace Src\Identity\Interface\Console;

use Illuminate\Console\Command;
use Src\Identity\Application\Admin\ChangeAdminRolePermissions;
use Src\Shared\Domain\Exceptions\DomainException;

/** Replaces the permissions of a role after showing what is added and removed. */
final class SetAdminRolePermissionsCommand extends Command
{
    use NamesTheOperator;

    protected $signature = 'identity:set-role-permissions {name : Role name} {--permission=* : A permission the role holds (repeat the option; the list replaces the current one)}';

    protected $description = 'Replace the permissions of an admin role';

    public function handle(ChangeAdminRolePermissions $change): int
    {
        $name = (string) $this->argument('name');
        $permissions = array_values(array_map('strval', (array) $this->option('permission')));

        try {
            $plan = $change->plan($name, $permissions);

            if ($plan->isEmpty()) {
                $this->info("The role {$name} already holds exactly these permissions.");

                return self::SUCCESS;
            }

            $this->line('Added: '.($plan->added === [] ? '-' : implode(', ', $plan->added)));
            $this->line('Removed: '.($plan->removed === [] ? '-' : implode(', ', $plan->removed)));

            if (! $this->confirm("Change the permissions of {$name}?")) {
                return self::FAILURE;
            }

            $change->execute($name, $permissions, $this->operator());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Permissions of {$name} changed; they apply to the next request of every administrator holding it.");

        return self::SUCCESS;
    }
}
