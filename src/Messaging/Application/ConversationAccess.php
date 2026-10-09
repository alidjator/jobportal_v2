<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\Conversation;
use Src\Messaging\Domain\ConversationRepository;
use Src\Messaging\Domain\SenderSide;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Who may take part in a conversation. A job seeker sees their own; a company admin sees every conversation of the company; any other member sees the ones they
 * started and the ones with a job seeker who applied to a job assigned to them. A conversation the caller may not see is a plain 404.
 */
final readonly class ConversationAccess
{
    public function __construct(
        private CompanyDirectory $companies,
        private ConversationRepository $conversations,
        private ApplicantRelations $applicants,
    ) {}

    /**
     * @throws AccessDenied `not-a-participant` or `company-suspended`
     */
    public function participant(Actor $actor): Participant
    {
        if ($actor->id === null) {
            throw new AccessDenied('Only a job seeker or a company member can use chat.', 'not-a-participant');
        }

        if ($actor->kind === ActorKind::JobSeeker) {
            return new Participant(SenderSide::Seeker, $actor->id);
        }

        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a job seeker or a company member can use chat.', 'not-a-participant');
        }

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return new Participant(SenderSide::Company, $actor->id, $actor->membership->companyId, $actor->isCompanyAdmin());
    }

    /** @throws AccessDenied `not-a-company-member` */
    public function companyMember(Actor $actor): Participant
    {
        try {
            $participant = $this->participant($actor);
        } catch (AccessDenied $e) {
            if ($e->errorCode() === 'company-suspended') {
                throw $e;
            }

            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        if (! $participant->isCompany()) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        return $participant;
    }

    /** @throws AccessDenied `job-seeker-only` */
    public function jobSeeker(Actor $actor): Participant
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can do this.', 'job-seeker-only');
        }

        return new Participant(SenderSide::Seeker, $actor->id);
    }

    public function audience(Participant $participant): ConversationAudience
    {
        $visible = $participant->isCompany() && ! $participant->isCompanyAdmin
            ? $this->applicants->applicantsVisibleToRecruiter((int) $participant->companyId, $participant->userId)
            : [];

        return new ConversationAudience($participant->side, $participant->userId, $participant->companyId, $participant->isCompanyAdmin, $visible);
    }

    /** @throws EntityNotFound */
    public function open(Participant $participant, int $conversationId, bool $forUpdate = false): Conversation
    {
        $conversation = $this->conversations->findById($conversationId, $forUpdate);

        if ($conversation === null || ! $this->canSee($participant, $conversation)) {
            throw EntityNotFound::of('Conversation', $conversationId);
        }

        return $conversation;
    }

    public function canSee(Participant $participant, Conversation $conversation): bool
    {
        if (! $participant->isCompany()) {
            return $conversation->jobSeekerUserId === $participant->userId;
        }

        if ($conversation->companyId !== $participant->companyId) {
            return false;
        }

        return $participant->isCompanyAdmin
            || $conversation->startedByUserId === $participant->userId
            || $this->applicants->canSeeApplicant($conversation->companyId, $participant->userId, false, $conversation->jobSeekerUserId);
    }
}
