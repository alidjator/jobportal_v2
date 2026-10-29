<?php

namespace Src\DemoData\Interface\Console;

use Illuminate\Console\Command;
use Src\DemoData\Application\DemoStructure;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Adds the demo organisation structure (unit types, grades, six legal entities with their cost centres, unit trees and positions) to the demo group that `demo:seed` made. It skips what exists, so it
 * can run again. Refused in production.
 */
final class SeedDemoStructureCommand extends Command
{
    protected $signature = 'demo:seed-structure';

    protected $description = 'Add the demo organisation structure (units and positions of six legal entities) to the demo group (refused in production)';

    public function handle(DemoStructure $structure): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('The demo data is for development databases only: demo:seed-structure is refused in production.');

            return self::FAILURE;
        }

        try {
            $created = $structure->build($structure->demoGroupAdmin());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Demo organisation structure written.');
        $this->table(['What', 'Created in this run'], array_map(static fn (string $what, int $count): array => [$what, $count], array_keys($created), array_values($created)));

        return self::SUCCESS;
    }
}
