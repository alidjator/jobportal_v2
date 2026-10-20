<?php

namespace Src\Company\Interface\Console;

use Illuminate\Console\Command;
use Src\Company\Application\ImportSummary\PurgeCompanyImportSummaries;

final class PurgeImportSummariesCommand extends Command
{
    protected $signature = 'company:purge-import-summaries';

    protected $description = 'Delete the import summaries of companies whose owner first signed in more than the retention period ago; safe to run again';

    public function handle(PurgeCompanyImportSummaries $purge): int
    {
        $this->info('Import summaries deleted: '.$purge->execute());

        return self::SUCCESS;
    }
}
