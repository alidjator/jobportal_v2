<?php

namespace Src\Platform\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Platform\Application\Queue\FailedJobs;
use Src\Platform\Application\Queue\QueueAlerts;
use Src\Platform\Application\Queue\QueueEmailTemplates;
use Src\Platform\Application\Queue\QueueStatus;

final readonly class MailQueueAlerts implements QueueAlerts
{
    /** A Markdown hard line break: the placeholder text is escaped, so list markup would not survive. */
    private const string BREAK = "  \n";

    public function __construct(private Mailer $mailer) {}

    public function failed(string $email, array $queues): void
    {
        $lines = array_map(function (FailedJobs $jobs): string {
            $classes = implode(', ', array_map(fn (string $class, int $count): string => "{$class} ({$count})", array_keys($jobs->classes), $jobs->classes));

            return "{$jobs->queue}: {$jobs->count} failed ({$classes}){$this->newest($jobs)}";
        }, $queues);

        $this->send($email, QueueEmailTemplates::FAILED, $lines);
    }

    public function stalled(string $email, array $queues): void
    {
        $lines = array_map(fn (QueueStatus $status): string => sprintf(
            '%s: oldest job waits %d minutes (%d waiting)',
            $status->queue,
            intdiv((int) $status->oldestWaitSeconds, 60),
            $status->waiting,
        ), $queues);

        $this->send($email, QueueEmailTemplates::STALLED, $lines);
    }

    public function recovered(string $email, array $queues): void
    {
        $this->send($email, QueueEmailTemplates::RECOVERED, $queues);
    }

    private function newest(FailedJobs $jobs): string
    {
        return $jobs->newestFailure === '' ? '' : self::BREAK.'Newest: '.$jobs->newestFailure;
    }

    /** @param  list<string>  $lines */
    private function send(string $email, string $template, array $lines): void
    {
        $this->mailer->to($email)->queue(new OperationsMail($template, ['queues' => implode(self::BREAK, $lines)]));
    }
}
