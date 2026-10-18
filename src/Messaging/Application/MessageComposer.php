<?php

namespace Src\Messaging\Application;

use Psr\Log\LoggerInterface;
use Src\Messaging\Domain\Attachment;
use Src\Messaging\Domain\AttachmentContent;
use Src\Messaging\Domain\AttachmentRepository;
use Src\Messaging\Domain\Conversation;
use Src\Messaging\Domain\ConversationRepository;
use Src\Messaging\Domain\Events\MessageSent;
use Src\Messaging\Domain\Message;
use Src\Messaging\Domain\MessageRepository;
use Src\Messaging\Domain\MessagingRules;
use Src\Messaging\Domain\ReadStateRepository;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Throwable;

/**
 * The common part of every send. `prepare` checks the text and the files and stores the files; `assertMayWrite` checks that the sender may write in a
 * conversation now; `deliver` (inside the caller's transaction) saves the message; `discard` removes the stored files of a send that did not happen.
 */
final readonly class MessageComposer
{
    private const string DIRECTORY = 'messaging/attachments';

    public function __construct(
        private AttachmentRepository $attachments,
        private MessageRepository $messages,
        private ConversationRepository $conversations,
        private ReadStateRepository $reads,
        private FileStorage $files,
        private Settings $settings,
        private Clock $clock,
        private ApplicantContacts $contacts,
        private CompanyDirectory $companies,
        private DailyMessageQuota $quota,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws BusinessRuleViolation `empty-message`, `body-too-long`, `too-many-attachments`, `file-too-large` or `invalid-attachment`
     */
    public function prepare(int $senderUserId, MessageInput $input): PreparedMessage
    {
        $body = $input->body === null ? null : trim($input->body);
        $body = $body === '' ? null : $body;

        if ($body !== null && mb_strlen($body) > MessagingRules::BODY_MAX_LENGTH) {
            throw new BusinessRuleViolation('A message can have at most '.MessagingRules::BODY_MAX_LENGTH.' characters.', 'body-too-long');
        }

        if ($body === null && $input->uploads === []) {
            throw new BusinessRuleViolation('A message needs a text or at least one file.', 'empty-message');
        }

        if (count($input->uploads) > MessagingRules::ATTACHMENTS_MAX) {
            throw new BusinessRuleViolation('A message can have at most '.MessagingRules::ATTACHMENTS_MAX.' files.', 'too-many-attachments');
        }

        $maxBytes = $this->settings->integer(MessagingSettings::MAX_ATTACHMENT_BYTES);
        $now = $this->clock->now();
        $stored = [];

        foreach ($input->uploads as $upload) {
            if (strlen($upload->contents) > $maxBytes) {
                throw new BusinessRuleViolation('A file is larger than the allowed '.$maxBytes.' bytes.', 'file-too-large', extensions: ['max_bytes' => $maxBytes]);
            }

            $mime = AttachmentContent::mimeOf($upload->contents);
            $stored[] = [new Attachment($senderUserId, AttachmentContent::cleanName($upload->name), self::DIRECTORY.'/'.bin2hex(random_bytes(16)).'.'.AttachmentContent::extensionOf($mime), $mime, strlen($upload->contents), $now), $upload->contents];
        }

        return new PreparedMessage($body, $this->store($stored));
    }

    /**
     * @throws AccessDenied `company-suspended`
     * @throws StateConflict `conversation-closed`, `conversation-blocked` or `recipient-unavailable`
     * @throws TooManyAttempts `daily-limit`
     */
    public function assertMayWrite(Participant $sender, Conversation $conversation): void
    {
        $conversation->assertOpen();

        if ($sender->isCompany()) {
            $conversation->assertNotBlockedForCompany();

            if (! $this->contacts->isActiveJobSeeker($conversation->jobSeekerUserId)) {
                throw new StateConflict('This job seeker no longer has an account that can receive messages.', 'recipient-unavailable');
            }

            $this->quota->lockAndAssertRoom($conversation->companyId);

            return;
        }

        if (! $this->companies->isActive($conversation->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }
    }

    /** Saves the message and moves the conversation's last message time and the sender's read pointer. Runs inside the caller's transaction. */
    public function deliver(Conversation $conversation, Participant $sender, PreparedMessage $prepared, bool $bulk = false): MessageSent
    {
        $now = $this->clock->now();
        $conversationId = (int) $conversation->id;

        $message = $this->messages->add(new Message($conversationId, $sender->side, $sender->userId, $prepared->body, $prepared->attachmentIds, $now));
        $this->conversations->update($conversation->withLastMessageAt($now));
        $this->reads->markRead($conversationId, $sender->userId, $message->id, $now);

        return new MessageSent($conversationId, (int) $message->id, $conversation->companyId, $conversation->jobSeekerUserId, $sender->side, $sender->userId, $bulk, $now);
    }

    /** Removes the stored files of a send that did not happen (the ones no message uses). A failure to delete is logged, never raised over the real error. */
    public function discard(PreparedMessage $prepared): void
    {
        if ($prepared->attachmentIds === []) {
            return;
        }

        try {
            foreach ($this->attachments->deleteUnlinked($prepared->attachmentIds) as $path) {
                $this->files->delete($path);
            }
        } catch (Throwable $e) {
            $this->logger->warning('The files of an unsent chat message could not be removed.', ['exception' => $e]);
        }
    }

    /**
     * @param  list<array{Attachment, string}>  $stored  file row and its bytes
     * @return list<int> the ids of the saved rows
     */
    private function store(array $stored): array
    {
        if ($stored === []) {
            return [];
        }

        $written = [];

        try {
            foreach ($stored as [$attachment, $contents]) {
                $this->files->put($attachment->path, $contents);
                $written[] = $attachment->path;
            }

            return array_map(fn (Attachment $saved): int => (int) $saved->id, $this->attachments->addAll(array_column($stored, 0)));
        } catch (Throwable $e) {
            foreach ($written as $path) {
                try {
                    $this->files->delete($path);
                } catch (Throwable) {
                    $this->logger->warning('A chat file could not be removed after a failed save.', ['path' => $path]);
                }
            }

            throw $e;
        }
    }
}
