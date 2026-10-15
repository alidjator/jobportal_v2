<?php

namespace Src\Messaging\Application\Export;

use DateTimeInterface;
use Src\Messaging\Application\ConversationAudience;
use Src\Messaging\Application\ConversationReader;
use Src\Messaging\Application\MessageReader;
use Src\Messaging\Application\MessageRow;
use Src\Messaging\Domain\AttachmentRepository;
use Src\Messaging\Domain\SenderSide;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Privacy\ExportedFile;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;

/**
 * The Messaging part of the personal data export: every conversation of the job seeker with its messages from both sides (a message deleted by its sender is
 * listed without its text), and the files the job seeker attached themselves. The files the company attached are the company's.
 */
final readonly class ConversationsDataExporter implements PersonalDataExporter
{
    public function __construct(
        private ConversationReader $conversations,
        private MessageReader $messages,
        private AttachmentRepository $attachments,
        private CompanyDirectory $companies,
    ) {}

    public function key(): string
    {
        return 'conversations';
    }

    public function export(int $userId): PersonalDataSection
    {
        $audience = new ConversationAudience(SenderSide::Seeker, $userId, null, false);
        $rows = [];
        $cursor = null;

        do {
            $page = $this->conversations->page($audience, new CursorRequest($cursor, PageRequest::MAX_PER_PAGE));
            array_push($rows, ...$page->items);
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn ($row): int => $row->companyId, $rows))));
        $files = [];
        $conversations = [];

        foreach ($rows as $row) {
            $messages = [];

            foreach (array_reverse($this->allMessages($row->id)) as $message) {
                $messages[] = $this->message($userId, $message, $files);
            }

            $conversations[] = [
                'company' => ($companies[$row->companyId] ?? null)?->name,
                'started_by' => $row->startedByUserId === $userId ? 'you' : 'company',
                'closed_at' => $row->closedAt?->format(DateTimeInterface::ATOM),
                'messages' => $messages,
            ];
        }

        return new PersonalDataSection($conversations, $files);
    }

    /** @return list<MessageRow> newest first */
    private function allMessages(int $conversationId): array
    {
        $messages = [];
        $cursor = null;

        do {
            $page = $this->messages->page($conversationId, new CursorRequest($cursor, PageRequest::MAX_PER_PAGE));
            array_push($messages, ...$page->items);
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return $messages;
    }

    /**
     * @param  list<ExportedFile>  $files  receives the job seeker's own attachments
     * @return array<string, mixed>
     */
    private function message(int $userId, MessageRow $message, array &$files): array
    {
        $own = $message->side === SenderSide::Seeker && $message->senderUserId === $userId;
        $attachments = [];

        foreach ($message->deletedAt === null ? $message->attachments : [] as $attachment) {
            $entry = ['name' => $attachment->name, 'mime' => $attachment->mime, 'size_bytes' => $attachment->sizeBytes, 'file' => null];
            $stored = $own ? $this->attachments->find($attachment->id) : null;

            if ($stored !== null && $stored->ownerUserId === $userId) {
                $archiveName = 'attachment-'.$attachment->id.(($extension = pathinfo($attachment->name, PATHINFO_EXTENSION)) === '' ? '' : '.'.$extension);
                $files[] = new ExportedFile($stored->path, $archiveName);
                $entry['file'] = 'files/conversations/'.$archiveName;
            }

            $attachments[] = $entry;
        }

        return [
            'from' => $own ? 'you' : 'company',
            'sent_at' => $message->createdAt->format(DateTimeInterface::ATOM),
            'deleted_at' => $message->deletedAt?->format(DateTimeInterface::ATOM),
            'text' => $message->deletedAt === null ? $message->body : null,
            'attachments' => $attachments,
        ];
    }
}
