<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Scorecard;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;

/** Adds the member names to scorecards with one account lookup. */
final readonly class ScorecardViews
{
    public function __construct(private CompanyMemberAccounts $accounts) {}

    /**
     * @param  list<Scorecard>  $scorecards
     * @return list<ScorecardView>
     */
    public function of(array $scorecards): array
    {
        $names = [];

        foreach ($this->accounts->summaries(array_values(array_unique(array_map(static fn (Scorecard $scorecard): int => $scorecard->memberId, $scorecards)))) as $userId => $summary) {
            $names[$userId] = $summary->name;
        }

        return array_map(static fn (Scorecard $scorecard): ScorecardView => new ScorecardView($scorecard, $names[$scorecard->memberId] ?? null), $scorecards);
    }

    public function one(Scorecard $scorecard): ScorecardView
    {
        return $this->of([$scorecard])[0];
    }
}
