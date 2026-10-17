<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRsvps;
use Src\Recruitment\Domain\RsvpAnswer;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;

/** Adds the interviewer names, and for the team the panel, to interviews with one lookup of each. */
final readonly class InterviewViews
{
    public function __construct(private CompanyMemberAccounts $accounts, private InterviewPanel $panel, private InterviewRsvps $rsvps) {}

    /**
     * @param  list<Interview>  $interviews
     * @return list<InterviewView>
     */
    public function of(array $interviews, bool $withPanel = false): array
    {
        $ids = array_values(array_filter(array_map(static fn (Interview $interview): ?int => $interview->id, $interviews)));
        $panels = $withPanel ? $this->panel->forInterviews($ids) : [];
        $answers = $withPanel ? $this->rsvps->forInterviews($ids) : [];

        $userIds = array_map(static fn (Interview $interview): int => $interview->interviewerId, $interviews);

        foreach ($panels as $members) {
            $userIds = [...$userIds, ...$members];
        }

        $names = [];

        foreach ($this->accounts->summaries(array_values(array_unique($userIds))) as $userId => $summary) {
            $names[$userId] = $summary->name;
        }

        return array_map(static fn (Interview $interview): InterviewView => new InterviewView(
            $interview,
            $names[$interview->interviewerId] ?? null,
            array_map(static fn (int $userId): array => ['id' => $userId, 'name' => $names[$userId] ?? null, 'rsvp' => $answers[(int) $interview->id][$userId] ?? RsvpAnswer::pending()], $panels[(int) $interview->id] ?? []),
            $withPanel ? ($answers[(int) $interview->id][$interview->interviewerId] ?? RsvpAnswer::pending()) : null,
        ), $interviews);
    }

    public function one(Interview $interview, bool $withPanel = false): InterviewView
    {
        return $this->of([$interview], $withPanel)[0];
    }
}
