<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * A job seeker writes to a company they have applied to (any application, whatever its status). When they already have a conversation with it the message goes
 * into that one. The company the job seeker applied to is not hidden from them, so a company that does not exist is the same 404 as one they never applied to.
 */
final readonly class StartSeekerConversation
{
    public function __construct(
        private ConversationAccess $access,
        private ConversationOpener $opener,
        private MessageComposer $composer,
        private ConversationReader $reader,
        private MessagingViews $views,
        private CompanyDirectory $companies,
        private ApplicantRelations $applicants,
        private TransactionManager $transactions,
        private EventDispatcher $events,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only` or `company-suspended`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation
     * @throws StateConflict `conversation-closed`
     */
    public function execute(Actor $actor, int $companyId, MessageInput $input): StartedConversation
    {
        $sender = $this->access->jobSeeker($actor);

        if (! $this->companies->exists($companyId) || ! $this->applicants->hasApplied($companyId, $sender->userId)) {
            throw EntityNotFound::of('Company', $companyId);
        }

        $prepared = $this->composer->prepare($sender->userId, $input);

        try {
            [$event, $created] = $this->transactions->run(function () use ($sender, $companyId, $prepared) {
                [$conversation, $created] = $this->opener->openPair($companyId, $sender->userId, $sender->userId);
                $this->composer->assertMayWrite($sender, $conversation);

                return [$this->composer->deliver($conversation, $sender, $prepared), $created];
            });
        } catch (Throwable $e) {
            $this->composer->discard($prepared);

            throw $e;
        }

        $this->events->dispatch([$event]);

        $row = $this->reader->one($this->access->audience($sender), $event->conversationId) ?? throw EntityNotFound::of('Conversation', $event->conversationId);

        return new StartedConversation($this->views->conversation($row, $sender->side), $created);
    }
}
