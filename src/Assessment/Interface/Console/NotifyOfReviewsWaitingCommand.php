<?php

namespace Src\Assessment\Interface\Console;

use Illuminate\Console\Command;
use Src\Assessment\Application\Notices\NotifyOfReviewsWaiting;

final class NotifyOfReviewsWaitingCommand extends Command
{
    protected $signature = 'assessment:notify-reviews-waiting';

    protected $description = 'Tell the psychologists of a provider that more reviews wait than when they were last told';

    public function handle(NotifyOfReviewsWaiting $notify): int
    {
        $count = $notify->execute();

        $this->info("Told {$count} psychologist".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
