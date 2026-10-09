<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\ConversationRepository;
use Src\Messaging\Domain\MessagingRules;
use Src\Messaging\Domain\ReadStateRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * The reminder email: for every unread stretch that began 15 minutes ago or more and was not reminded, one email (never more than one until the conversation is
 * read). A person who switched the `chat_message` email off, or whose account is gone, is skipped but still marked, so they are not looked at again.
 */
final readonly class SendUnreadEmails
{
    private const int BATCH = 200;

    public function __construct(
        private ReadStateRepository $reads,
        private ConversationRepository $conversations,
        private ApplicantContacts $contacts,
        private NotificationPreferences $preferences,
        private NameLookup $names,
        private UnreadEmailSender $emails,
        private Clock $clock,
    ) {}

    /** @return int how many emails were sent */
    public function execute(): int
    {
        $now = $this->clock->now();
        $sent = 0;

        foreach ($this->reads->dueForReminder($now->modify('-'.MessagingRules::EMAIL_DELAY_MINUTES.' minutes'), self::BATCH) as $due) {
            $conversation = $this->conversations->findById($due->conversationId);
            $contact = $this->contacts->contact($due->userId);

            if ($conversation !== null && $contact instanceof ApplicantContact && $this->preferences->isEnabled($due->userId, MessagingNotificationType::UNREAD_PREFERENCE, NotificationChannel::Email)) {
                $this->emails->send($contact, MessagingEmailTemplates::UNREAD, ['company_name' => $this->names->company($conversation->companyId)]);
                $sent++;
            }

            $this->reads->markEmailed($due->readStateId, $now);
        }

        return $sent;
    }
}
