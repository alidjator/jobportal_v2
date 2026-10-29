<?php

namespace Src\DemoData\Interface\Console;

use Illuminate\Console\Command;
use Src\DemoData\Application\DemoWorkforce;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Adds the demo workforce (approval flows, manpower plans and requisitions of the two tenants, taken through their approval paths) to the demo group that `demo:seed` and `demo:seed-structure` made. It does
 * nothing when the group already has an approval flow. Refused in production.
 */
final class SeedDemoWorkforceCommand extends Command
{
    protected $signature = 'demo:seed-workforce';

    protected $description = 'Add the demo workforce (approval flows, plans, requisitions and their approvals) to the demo group (refused in production)';

    public function handle(DemoWorkforce $workforce): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('The demo data is for development databases only: demo:seed-workforce is refused in production.');

            return self::FAILURE;
        }

        try {
            $written = $workforce->build();
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (array_sum($written) === 0) {
            $this->info('The demo workforce is already there; nothing was written.');

            return self::SUCCESS;
        }

        $this->info('Demo workforce written.');
        $this->table(['What', 'Written in this run'], array_map(static fn (string $what, int $count): array => [$what, $count], array_keys($written), array_values($written)));

        return self::SUCCESS;
    }
}
