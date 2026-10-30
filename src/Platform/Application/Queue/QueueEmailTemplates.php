<?php

namespace Src\Platform\Application\Queue;

use Src\Shared\Application\Email\EmailTemplateDefinition;
use Src\Shared\Application\Email\EmailVariable;
use Src\Shared\Domain\TranslatedText;

/** The queue alert emails, registered in the shared catalog by the Platform service provider. Bodies are Markdown with `{{variable}}` placeholders. */
final class QueueEmailTemplates
{
    public const string FAILED = 'platform.queue_failed_jobs';

    public const string STALLED = 'platform.queue_stalled';

    public const string RECOVERED = 'platform.queue_recovered';

    /** @return list<EmailTemplateDefinition> */
    public static function definitions(): array
    {
        return [
            new EmailTemplateDefinition(
                self::FAILED,
                'Sent to the operations address when queue jobs failed, at most once an hour per queue. Holds counts and the first line of the newest error, never a payload.',
                [new EmailVariable('queues', 'The queues, one per line', "default: 3 failed (App\\Jobs\\SendMail (3))  \nNewest: Connection refused", required: true)],
                TranslatedText::fromArray(['id' => 'Peringatan antrean: ada job yang gagal', 'en' => 'Queue alert: jobs failed']),
                TranslatedText::fromArray([
                    'id' => "Job antrean berikut gagal:\n\n{{queues}}\n\nLihat `php artisan queue:failed` dan kartu operasi di dasbor admin.",
                    'en' => "These queue jobs failed:\n\n{{queues}}\n\nSee `php artisan queue:failed` and the operations card on the admin dashboard.",
                ]),
            ),
            new EmailTemplateDefinition(
                self::STALLED,
                'Sent to the operations address when the oldest due job of a queue waits more than 10 minutes.',
                [new EmailVariable('queues', 'The queues, one per line', 'bulk: oldest job waits 14 minutes (230 waiting)', required: true)],
                TranslatedText::fromArray(['id' => 'Peringatan antrean: antrean macet', 'en' => 'Queue alert: a queue is stalled']),
                TranslatedText::fromArray([
                    'id' => "Antrean berikut macet:\n\n{{queues}}\n\nPeriksa worker (`supervisorctl status`).",
                    'en' => "These queues are stalled:\n\n{{queues}}\n\nCheck the workers (`supervisorctl status`).",
                ]),
            ),
            new EmailTemplateDefinition(
                self::RECOVERED,
                'Sent to the operations address when a stalled queue moves again.',
                [new EmailVariable('queues', 'The queues, one per line', 'bulk', required: true)],
                TranslatedText::fromArray(['id' => 'Antrean berjalan lagi', 'en' => 'Queue recovered']),
                TranslatedText::fromArray([
                    'id' => "Antrean berikut berjalan lagi:\n\n{{queues}}",
                    'en' => "These queues are moving again:\n\n{{queues}}",
                ]),
            ),
        ];
    }
}
