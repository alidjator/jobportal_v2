<?php

namespace Src\Assessment\Application\Messages;

use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportMessage;
use Src\Assessment\Domain\ReportMessageAuthor;
use Src\Assessment\Domain\ReportMessageRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Clock;

/** Builds the thread of a report for a reader: the messages with the names of their authors. */
final readonly class ReportThreads
{
    public function __construct(
        private ReportMessageRepository $messages,
        private CompanyMemberAccounts $members,
        private PsychologistAccounts $psychologists,
        private Clock $clock,
    ) {}

    public function build(Report $report, ReportMessageAuthor $reader, int $readerUserId): ReportThread
    {
        $messages = $this->messages->ofReport((int) $report->id);
        $names = $this->namesOf($messages);

        return new ReportThread(
            (int) $report->id,
            array_map(static fn (ReportMessage $message): ReportMessageView => new ReportMessageView(
                $message,
                $names[$message->author->value][$message->authorUserId] ?? '',
                $message->author === $reader && $message->authorUserId === $readerUserId,
            ), $messages),
            $report->acceptsQuestions($this->clock->now()),
            ReportThread::closesOn($report),
        );
    }

    /**
     * @param  list<ReportMessage>  $messages
     * @return array<string, array<int, string>> the author names by kind and account id
     */
    private function namesOf(array $messages): array
    {
        $ids = static fn (ReportMessageAuthor $kind): array => array_values(array_unique(array_map(
            static fn (ReportMessage $message): int => $message->authorUserId,
            array_filter($messages, static fn (ReportMessage $message): bool => $message->author === $kind),
        )));

        $names = [ReportMessageAuthor::Company->value => [], ReportMessageAuthor::Psychologist->value => []];

        foreach ($this->members->summaries($ids(ReportMessageAuthor::Company)) as $id => $summary) {
            $names[ReportMessageAuthor::Company->value][$id] = $summary->name;
        }

        foreach ($this->psychologists->summaries($ids(ReportMessageAuthor::Psychologist)) as $id => $summary) {
            $names[ReportMessageAuthor::Psychologist->value][$id] = $summary->name;
        }

        return $names;
    }
}
