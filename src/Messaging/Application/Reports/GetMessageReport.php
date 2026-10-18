<?php

namespace Src\Messaging\Application\Reports;

use Src\Messaging\Application\NameLookup;
use Src\Messaging\Domain\ConversationRepository;
use Src\Messaging\Domain\MessageReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** An admin holding `messages.moderate` reads a report with its snapshot. The live thread is never shown; a cleared snapshot reads as null. */
final readonly class GetMessageReport
{
    public function __construct(
        private MessageReportRepository $reports,
        private ConversationRepository $conversations,
        private NameLookup $names,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): MessageReportDetail
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can read a conversation report.');
        }

        $report = $this->reports->find($id) ?? throw EntityNotFound::of('MessageReport', $id);
        $conversation = $this->conversations->findById($report->conversationId) ?? throw EntityNotFound::of('Conversation', $report->conversationId);

        return new MessageReportDetail(
            $report, $conversation->companyId, $this->names->company($conversation->companyId),
            $conversation->jobSeekerUserId, $this->names->user($conversation->jobSeekerUserId),
        );
    }
}
