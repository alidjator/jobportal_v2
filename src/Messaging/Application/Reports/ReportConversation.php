<?php

namespace Src\Messaging\Application\Reports;

use Src\Messaging\Application\ConversationAccess;
use Src\Messaging\Application\MessageReader;
use Src\Messaging\Application\MessageRow;
use Src\Messaging\Domain\MessageReport;
use Src\Messaging\Domain\MessageReportRepository;
use Src\Messaging\Domain\ReporterSide;
use Src\Messaging\Domain\ReportReason;
use Src\Messaging\Domain\SenderSide;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A party of a conversation (the job seeker, or a company member who may see it) reports it. One open report per reporter and conversation. The report keeps a snapshot
 * of the reported message (or the latest one) and up to 10 earlier messages, so an admin reads what was said even if the sender deletes it later. Closed or blocked
 * conversations can be reported too.
 */
final readonly class ReportConversation
{
    public const int NOTE_MAX = 1000;

    public const int SNAPSHOT_MESSAGES = 11;

    public function __construct(
        private ConversationAccess $access,
        private MessageReader $messages,
        private MessageReportRepository $reports,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-participant`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the conversation is not one the caller may see
     * @throws StateConflict `already-reported`
     * @throws BusinessRuleViolation `invalid-report` with `errors` per field
     */
    public function execute(Actor $actor, int $conversationId, ReportReason $reason, ?string $note, ?int $messageId): MessageReport
    {
        $reporter = $this->access->participant($actor);
        $note = $note === null || trim($note) === '' ? null : trim($note);
        $this->assertNote($reason, $note);

        return $this->transactions->run(function () use ($reporter, $conversationId, $reason, $note, $messageId): MessageReport {
            // The conversation row is locked, so two reports at once cannot both pass the check below.
            $this->access->open($reporter, $conversationId, forUpdate: true);

            if ($this->reports->hasOpenReport($conversationId, $reporter->userId)) {
                throw new StateConflict('You have already reported this conversation.', 'already-reported');
            }

            if ($messageId !== null && $this->messages->one($messageId)?->conversationId !== $conversationId) {
                $message = 'The message is not part of this conversation.';

                throw new BusinessRuleViolation($message, 'invalid-report', extensions: ['errors' => ['message_id' => [$message]]]);
            }

            return $this->reports->add(
                $conversationId, $messageId, ReporterSide::of($reporter->side), $reporter->userId, $reason, $note,
                $this->snapshot($conversationId, $messageId), $this->clock->now(),
            );
        });
    }

    /** @throws BusinessRuleViolation `invalid-report` */
    private function assertNote(ReportReason $reason, ?string $note): void
    {
        if ($reason === ReportReason::Other && $note === null) {
            throw new BusinessRuleViolation('Tell us what is wrong with this conversation.', 'invalid-report', extensions: ['errors' => ['note' => ['Tell us what is wrong with this conversation.']]]);
        }

        if ($note !== null && mb_strlen($note) > self::NOTE_MAX) {
            $message = 'The note can have at most '.self::NOTE_MAX.' characters.';

            throw new BusinessRuleViolation($message, 'invalid-report', extensions: ['errors' => ['note' => [$message]]]);
        }
    }

    /**
     * The reported message and up to 10 earlier ones, oldest first: text, sender side and time; files by name only (the files themselves are not kept).
     *
     * @return list<array<string, mixed>>
     */
    private function snapshot(int $conversationId, ?int $messageId): array
    {
        $rows = array_reverse($this->messages->upTo($conversationId, $messageId, self::SNAPSHOT_MESSAGES));
        $reported = $messageId ?? ($rows === [] ? null : $rows[array_key_last($rows)]->id);

        return array_map(fn (MessageRow $row): array => [
            'message_id' => $row->id,
            'side' => $row->side === SenderSide::Seeker ? 'job_seeker' : 'company',
            'sent_at' => $row->createdAt->format(DATE_ATOM),
            'text' => $row->deletedAt === null ? $row->body : null,
            'attachments' => $row->deletedAt === null ? array_map(fn ($attachment): string => $attachment->name, $row->attachments) : [],
            'deleted' => $row->deletedAt !== null,
            'reported' => $messageId !== null && $row->id === $reported,
        ], $rows);
    }
}
