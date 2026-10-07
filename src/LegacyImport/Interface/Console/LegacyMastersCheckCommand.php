<?php

namespace Src\LegacyImport\Interface\Console;

use Illuminate\Console\Command;

/** Shows which legacy master items found no counterpart in the new masters, so a mapping file can be written before the real import. Read only. */
final class LegacyMastersCheckCommand extends Command
{
    protected $signature = 'legacy:masters:check {--map= : A mapping file for the masters (a JSON file)}';

    protected $description = 'List the legacy master items that match no new master (read only)';

    public function handle(): int
    {
        $arguments = ['entity' => 'masters'];

        if ($this->option('map') !== null) {
            $arguments['--map'] = ['masters='.$this->option('map')];
        }

        return $this->call('legacy:import', $arguments);
    }
}
