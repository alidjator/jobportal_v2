<?php

namespace Src\Messaging\Application;

use Psr\Log\LoggerInterface;
use Src\Messaging\Domain\AttachmentRepository;
use Src\Messaging\Domain\MessageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The file of a message the caller may see. A deleted message has no files; a file that is missing on the disk is reported and answered as not found. */
final readonly class GetAttachment
{
    public function __construct(
        private ConversationAccess $access,
        private MessageRepository $messages,
        private AttachmentRepository $attachments,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $messageId, int $attachmentId): AttachmentFile
    {
        $participant = $this->access->participant($actor);
        $message = $this->messages->findById($messageId) ?? throw EntityNotFound::of('Message', $messageId);
        $this->access->open($participant, $message->conversationId);

        if ($message->isDeleted() || ! $this->attachments->isLinked($messageId, $attachmentId)) {
            throw EntityNotFound::of('Attachment', $attachmentId);
        }

        $attachment = $this->attachments->find($attachmentId) ?? throw EntityNotFound::of('Attachment', $attachmentId);
        $contents = $this->files->get($attachment->path);

        if ($contents === null) {
            $this->logger->error('The file of a chat attachment is missing.', ['attachment_id' => $attachmentId, 'path' => $attachment->path]);

            throw EntityNotFound::of('Attachment', $attachmentId);
        }

        return new AttachmentFile($contents, $attachment->name, $attachment->mime);
    }
}
