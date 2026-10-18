<?php

namespace Src\Platform\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Src\Platform\Application\Scheduler\ScheduledTaskStatus;
use Src\Platform\Application\Scheduler\SchedulerAlerts;
use Src\Platform\Application\Scheduler\SchedulerEmailTemplates;

final readonly class MailSchedulerAlerts implements SchedulerAlerts
{
    /** A Markdown hard line break: the placeholder text is escaped, so list markup would not survive. */
    private const string BREAK = "  \n";

    public function __construct(private Mailer $mailer) {}

    public function raised(string $email, array $tasks): void
    {
        $lines = array_map(function (ScheduledTaskStatus $task): string {
            $reasons = implode(' and ', array_filter([$task->late ? 'late' : null, $task->failing ? 'failing' : null]));
            $failure = $task->failing && $task->lastFailure !== null ? ': '.$task->lastFailure : '';

            return "{$task->task} ({$reasons}){$failure}";
        }, $tasks);

        $this->mailer->to($email)->queue(new OperationsMail(SchedulerEmailTemplates::ALERT, ['tasks' => implode(self::BREAK, $lines)]));
    }

    public function recovered(string $email, array $tasks): void
    {
        $this->mailer->to($email)->queue(new OperationsMail(SchedulerEmailTemplates::RECOVERED, ['tasks' => implode(self::BREAK, $tasks)]));
    }
}
