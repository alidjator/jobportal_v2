<?php

namespace Src\Messaging\Application;

use Src\Messaging\Domain\SenderSide;

/** Turns stored rows into views: adds the names, and the online flag for a job seeker. */
final readonly class MessagingViews
{
    public function __construct(private NameLookup $names, private CompanyPresence $presence) {}

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
        return new ConversationView(
            $row,
            $this->names->company($row->companyId),
            $this->names->user($row->jobSeekerUserId),
            $row->lastMessage === null ? null : $this->message($row->lastMessage),
            $viewerSide === SenderSide::Seeker ? $this->presence->isOnline($row->companyId) : null,
        );
    }

    /**
     * @param  list<ConversationRow>  $rows
     * @return list<ConversationView>
     */
    public function conversations(array $rows, SenderSide $viewerSide): array
    {
        return array_map(fn (ConversationRow $row): ConversationView => $this->conversation($row, $viewerSide), $rows);
    }
}
