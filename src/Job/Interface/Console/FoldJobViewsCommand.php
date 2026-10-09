<?php

namespace Src\Job\Interface\Console;

use Illuminate\Console\Command;
use Src\Job\Application\Views\FoldJobViews;

final class FoldJobViewsCommand extends Command
{
    protected $signature = 'job:fold-views';

    protected $description = 'Fold job views older than 90 days into the daily counts and delete the raw rows';

    public function handle(FoldJobViews $fold): int
    {
        $count = $fold->execute();

        $this->info("Folded and removed {$count} view row".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
