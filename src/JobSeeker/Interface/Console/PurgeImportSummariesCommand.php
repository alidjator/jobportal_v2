<?php

namespace Src\JobSeeker\Interface\Console;

use Illuminate\Console\Command;
use Src\JobSeeker\Application\ImportSummary\PurgeImportSummaries;

final class PurgeImportSummariesCommand extends Command
{
    protected $signature = 'job-seeker:purge-import-summaries';

    protected $description = 'Delete the import summaries of job seekers who first signed in more than the retention period ago; safe to run again';

    public function handle(PurgeImportSummaries $purge): int
    {
        $this->info('Import summaries deleted: '.$purge->execute());

        return self::SUCCESS;
    }
}
