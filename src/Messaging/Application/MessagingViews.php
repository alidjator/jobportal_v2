<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\SenderSide;
use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Candidate\CandidateRef;

/** Turns stored rows into views: adds the names, the online flag for a job seeker and, for the company, the profile picture of a job seeker it can find. */
final readonly class MessagingViews
{
    public function __construct(private NameLookup $names, private CompanyPresence $presence, private CandidateDirectory $candidates) {}

    public function message(MessageRow $row): MessageView
    {
        return new MessageView($row, $this->names->user($row->senderUserId));
    }

    /**
     * @param  list<MessageRow>  $rows
     * @return list<MessageView>
     */
    public function messages(array $rows): array
    {
        return array_map($this->message(...), $rows);
    }

    public function conversation(ConversationRow $row, SenderSide $viewerSide): ConversationView
    {
        return $this->conversations([$row], $viewerSide)[0];
    }

    /**
     * @param  list<ConversationRow>  $rows
     * @return list<ConversationView>
     */
    public function conversations(array $rows, SenderSide $viewerSide): array
    {
        $candidates = $viewerSide === SenderSide::Company ? $this->candidatesOf($rows) : [];

        return array_map(fn (ConversationRow $row): ConversationView => new ConversationView(
            $row,
            $this->names->company($row->companyId),
            $this->names->user($row->jobSeekerUserId),
            $row->lastMessage === null ? null : $this->message($row->lastMessage),
            $viewerSide === SenderSide::Seeker ? $this->presence->isOnline($row->companyId) : null,
            $candidates[$row->companyId.':'.$row->jobSeekerUserId] ?? null,
        ), $rows);
    }

    /**
     * The job seekers of these conversations that their company can find right now (the rule of the candidate search), by `companyId:userId`; one read for each company.
     *
     * @param  list<ConversationRow>  $rows
     * @return array<string, CandidateRef>
     */
    private function candidatesOf(array $rows): array
    {
        $byCompany = [];

        foreach ($rows as $row) {
            $byCompany[$row->companyId][] = $row->jobSeekerUserId;
        }

        $found = [];

        foreach ($byCompany as $companyId => $userIds) {
            $findable = $this->candidates->findableIds($userIds, $companyId);
            $refs = $findable === [] ? [] : $this->candidates->refs($findable);

            foreach ($refs as $userId => $ref) {
                $found[$companyId.':'.$userId] = $ref;
            }
        }

        return $found;
    }
}
