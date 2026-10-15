<?php

namespace Src\Company\Interface\Console;

use Illuminate\Console\Command;
use Src\Company\Application\NameChange\PurgeNameChangeDocuments;

final class PurgeNameChangeDocumentsCommand extends Command
{
    protected $signature = 'company:purge-name-change-documents';

    protected $description = 'Delete the proof of a name change request 90 days after the request was decided';

    public function handle(PurgeNameChangeDocuments $purge): int
    {
        $count = $purge->execute();

        $this->info("Deleted {$count} document(s).");

        return self::SUCCESS;
    }
}
