<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Throwable;

/**
 * A company member writes to a job seeker, who need not have applied. When the company and the job seeker already have a conversation the message goes into it,
 * if the member may see it; a conversation another member started with a job seeker who never applied is not theirs to use (409 `conversation-exists`).
 */
final readonly class StartCompanyConversation
{
    public function __construct(
        private ConversationAccess $access,
        private ConversationOpener $opener,
        private MessageComposer $composer,
        private ConversationReader $reader,
        private MessagingViews $views,
        private ApplicantContacts $contacts,
        private TransactionManager $transactions,
        private EventDispatcher $events,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound when the account is not an active job seeker
     * @throws BusinessRuleViolation
     * @throws StateConflict `conversation-exists`, `conversation-closed` or `recipient-unavailable`
     * @throws TooManyAttempts `daily-limit`
     */
    public function execute(Actor $actor, int $jobSeekerUserId, MessageInput $input): StartedConversation
    {
        $sender = $this->access->companyMember($actor);

        if (! $this->contacts->isActiveJobSeeker($jobSeekerUserId)) {
            throw EntityNotFound::of('Job seeker', $jobSeekerUserId);
        }

        $prepared = $this->composer->prepare($sender->userId, $input);

        try {
            [$event, $created] = $this->transactions->run(function () use ($sender, $jobSeekerUserId, $prepared) {
                [$conversation, $created] = $this->opener->openPair((int) $sender->companyId, $jobSeekerUserId, $sender->userId);

                if (! $this->access->canSee($sender, $conversation)) {
                    throw new StateConflict('This company already has a conversation with this job seeker, started by another member.', 'conversation-exists');
                }

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
