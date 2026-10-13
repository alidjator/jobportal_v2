<?php

namespace Src\Identity\Interface\Console;

use DateTimeInterface;
use Illuminate\Console\Command;
use Src\Identity\Application\Admin\AdminSummary;
use Src\Identity\Application\Admin\ListAdmins;

final class ListAdminsCommand extends Command
{
    protected $signature = 'identity:list-admins';

    protected $description = 'List the administrators with role, status, second factor and last sign-in';

    public function handle(ListAdmins $list): int
    {
        $this->table(['Id', 'Email', 'Name', 'Role', 'Status', 'Second factor', 'Last sign-in'], array_map(
            fn (AdminSummary $admin): array => [
                $admin->id,
                $admin->email,
                $admin->name,
                $admin->role ?? '-',
                $admin->status->value,
                $admin->hasTwoFactor ? 'yes' : 'no',
                $admin->lastLoginAt?->format(DateTimeInterface::ATOM) ?? '-',
            ],
            $list->execute(),
        ));

        return self::SUCCESS;
    }
}
