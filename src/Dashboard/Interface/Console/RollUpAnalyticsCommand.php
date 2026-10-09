<?php

namespace Src\Dashboard\Interface\Console;

use Illuminate\Console\Command;
use Src\Dashboard\Application\RollUpAnalytics;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class RollUpAnalyticsCommand extends Command
{
    protected $signature = 'analytics:rollup {--from= : First day to rebuild (Y-m-d, Jakarta)} {--to= : Last day to rebuild (Y-m-d, Jakarta); yesterday by default} {--backfill : Start at the first day with data}';

    protected $description = 'Rebuild the daily analytics summaries (yesterday by default); safe to run again for any past day';

    public function handle(RollUpAnalytics $rollUp): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        try {
            $days = $rollUp->execute(is_string($from) && $from !== '' ? $from : null, is_string($to) && $to !== '' ? $to : null, (bool) $this->option('backfill'));
        } catch (BusinessRuleViolation $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Rebuilt {$days} summary day".($days === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
