<?php

namespace Src\DemoData\Interface\Console;

use Illuminate\Console\Command;
use Src\DemoData\Application\DemoPresets;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Adds the demo screening presets (seven for the verified company, two for the other) to the demo group that `demo:seed` made, with the few library questions they need. It skips a question by its text and a preset
 * by its name, so it can run again. Refused in production.
 */
final class SeedDemoPresetsCommand extends Command
{
    protected $signature = 'demo:seed-presets';

    protected $description = 'Add the demo screening presets and the questions they need to the demo companies (refused in production)';

    public function handle(DemoPresets $presets): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('The demo data is for development databases only: demo:seed-presets is refused in production.');

            return self::FAILURE;
        }

        try {
            $made = $presets->build();
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Demo screening presets written.');
        $this->table(['What', 'Made in this run'], array_map(static fn (string $what, int $count): array => [$what, $count], array_keys($made), array_values($made)));

        return self::SUCCESS;
    }
}
