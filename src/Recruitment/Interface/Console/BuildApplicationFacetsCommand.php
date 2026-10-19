<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\BuildApplicationFacets;

final class BuildApplicationFacetsCommand extends Command
{
    protected $signature = 'recruitment:build-application-facets';

    protected $description = 'Build the search facets of every application that has none, from its stored profile snapshot (safe to run again)';

    public function handle(BuildApplicationFacets $build): int
    {
        $built = $build->execute();

        $this->info("Built the facets of {$built} application".($built === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
