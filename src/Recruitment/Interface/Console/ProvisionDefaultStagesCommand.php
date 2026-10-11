<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\ProvisionStagesForExistingCompanies;

final class ProvisionDefaultStagesCommand extends Command
{
    protected $signature = 'recruitment:provision-default-stages';

    protected $description = 'Give the eight default application stages to every company that has none, and the default assessment stage to every company that lacks it';

    public function handle(ProvisionStagesForExistingCompanies $provision): int
    {
        $checked = $provision->execute();

        $this->info("Checked {$checked} compan".($checked === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
