<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationNote;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;

/** Adds the author names to notes with one account lookup. */
final readonly class ApplicationNoteViews
{
    public function __construct(private CompanyMemberAccounts $accounts) {}

    /**
     * @param  list<ApplicationNote>  $notes
     * @return list<NoteView>
     */
    public function of(array $notes): array
    {
        $names = [];

        foreach ($this->accounts->summaries(array_values(array_unique(array_map(static fn (ApplicationNote $note): int => $note->authorId, $notes)))) as $userId => $summary) {
            $names[$userId] = $summary->name;
        }

        return array_map(static fn (ApplicationNote $note): NoteView => new NoteView($note, $names[$note->authorId] ?? null), $notes);
    }
}
