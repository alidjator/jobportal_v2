<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\Conversation;
use Src\Messaging\Domain\MessageRepository;
use Src\Messaging\Domain\SenderSide;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Recruitment\ApplicantRelations;

/**
 * Who is told about a new message. A message from the company goes to the job seeker. A message from the job seeker goes to the member of the company who last
 * wrote in the conversation, else the one who started it, else the members assigned to the job seeker's latest application (each only while still a member).
 */
final readonly class MessageRecipients
{
    public function __construct(
        private MessageRepository $messages,
        private CompanyTeam $team,
        private ApplicantRelations $applicants,
        private ApplicantContacts $contacts,
    ) {}

    /** @return list<int> */
    public function of(Conversation $conversation, SenderSide $senderSide): array
    {
        if ($senderSide === SenderSide::Company) {
            return $this->contacts->contact($conversation->jobSeekerUserId) === null ? [] : [$conversation->jobSeekerUserId];
        }

        $members = $this->team->roles($conversation->companyId);
        $lastWriter = $this->messages->lastWriter((int) $conversation->id, SenderSide::Company);

        if ($lastWriter !== null && isset($members[$lastWriter])) {
            return [$lastWriter];
        }

        if (isset($members[$conversation->startedByUserId])) {
            return [$conversation->startedByUserId];
        }

        return array_values(array_filter(
            $this->applicants->assigneesOfLatestApplication($conversation->companyId, $conversation->jobSeekerUserId),
            fn (int $userId): bool => isset($members[$userId]),
        ));
    }
}
