<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Interview;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;

/** Adds the interviewer names to interviews with one account lookup. */
final readonly class InterviewViews
{
    public function __construct(private CompanyMemberAccounts $accounts) {}

    /**
     * @param  list<Interview>  $interviews
     * @return list<InterviewView>
     */
    public function of(array $interviews): array
    {
        $names = [];

        foreach ($this->accounts->summaries(array_values(array_unique(array_map(static fn (Interview $interview): int => $interview->interviewerId, $interviews)))) as $userId => $summary) {
            $names[$userId] = $summary->name;
        }

        return array_map(static fn (Interview $interview): InterviewView => new InterviewView($interview, $names[$interview->interviewerId] ?? null), $interviews);
    }

    public function one(Interview $interview): InterviewView
    {
        return $this->of([$interview])[0];
    }
}
