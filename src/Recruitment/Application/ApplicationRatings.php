<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRatingRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;

/** Builds the rating view of an application for one viewer. */
final readonly class ApplicationRatings
{
    public function __construct(private ApplicationRatingRepository $ratings, private CompanyMemberAccounts $accounts) {}

    public function view(int $applicationId, int $viewerId): ApplicationRatingView
    {
        $stars = $this->ratings->forApplication($applicationId);
        $names = [];

        foreach ($this->accounts->summaries(array_keys($stars)) as $userId => $summary) {
            $names[$userId] = $summary->name;
        }

        $members = [];

        foreach ($stars as $userId => $value) {
            $members[] = new MemberRating($userId, $names[$userId] ?? null, $value);
        }

        return new ApplicationRatingView(
            $stars === [] ? null : round(array_sum($stars) / count($stars), 1),
            count($stars),
            $stars[$viewerId] ?? null,
            $members,
        );
    }
}
