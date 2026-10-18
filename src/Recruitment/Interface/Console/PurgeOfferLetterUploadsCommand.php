<?php

namespace Src\Recruitment\Interface\Console;

use Illuminate\Console\Command;
use Src\Recruitment\Application\PurgeOfferLetterUploads;

final class PurgeOfferLetterUploadsCommand extends Command
{
    protected $signature = 'recruitment:purge-offer-letter-uploads';

    protected $description = 'Delete the company offer letters that were uploaded more than 24 hours ago and that no offer used';

    public function handle(PurgeOfferLetterUploads $purge): int
    {
        $count = $purge->execute();

        $this->info("Deleted {$count} offer letter upload".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
