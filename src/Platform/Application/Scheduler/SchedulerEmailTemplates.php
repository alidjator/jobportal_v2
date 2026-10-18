<?php

namespace Src\Platform\Application\Scheduler;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The scheduler alert emails, registered in the shared catalog by the Platform service provider. Bodies are Markdown with `{{variable}}` placeholders. */
final class SchedulerEmailTemplates
{
    public const string ALERT = 'platform.scheduler_alert';

    public const string RECOVERED = 'platform.scheduler_recovered';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        $variables = [new EmailVariable('tasks', 'The tasks, one per line', "app:example (late)  \napp:other (failing)", required: true)];

        return [
            new EmailTemplateDefinition(
                self::ALERT,
                'Sent to the operations address when a scheduled task turned late or started failing.',
                $variables,
                TranslatedText::fromArray(['id' => 'Peringatan scheduler: ada tugas terlambat atau gagal', 'en' => 'Scheduler alert: a task is late or failing']),
                TranslatedText::fromArray([
                    'id' => "Tugas terjadwal berikut terlambat atau gagal:\n\n{{tasks}}\n\nPeriksa kartu operasi di dasbor admin dan crontab scheduler.",
                    'en' => "These scheduled tasks are late or failing:\n\n{{tasks}}\n\nCheck the operations card on the admin dashboard and the scheduler crontab.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::RECOVERED,
                'Sent to the operations address when tasks that raised an alert are healthy again.',
                $variables,
                TranslatedText::fromArray(['id' => 'Scheduler pulih', 'en' => 'Scheduler recovered']),
                TranslatedText::fromArray([
                    'id' => "Tugas terjadwal berikut sehat kembali:\n\n{{tasks}}",
                    'en' => "These scheduled tasks are healthy again:\n\n{{tasks}}",
                ]),
            ),
        ];
    }
}
