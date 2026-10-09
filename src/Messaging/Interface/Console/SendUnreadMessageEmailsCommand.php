<?php

namespace Src\Messaging\Interface\Console;

use Illuminate\Console\Command;
use Src\Messaging\Application\SendUnreadEmails;

final class SendUnreadMessageEmailsCommand extends Command
{
    protected $signature = 'messaging:send-unread-emails';

    protected $description = 'Email the people who still have an unread chat message 15 minutes after it arrived (one email per conversation until they read it)';

    public function handle(SendUnreadEmails $send): int
    {
        $this->info('Unread-message emails sent: '.$send->execute());

        return self::SUCCESS;
    }
}
