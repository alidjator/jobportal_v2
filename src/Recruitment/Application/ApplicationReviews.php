<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ReviewAnswer;
use Src\Recruitment\Domain\ReviewRequest;
use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Recruitment\Domain\ReviewRequestStatus;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;

/**
 * What the shortlist reviews say about applications, for one viewer (`docs/design/phase-8-hiring-managers.md` section 3). The lock holds here too: a reviewer sees the other reviewers'
 * answers about an application only after sending their own about it; the requester and members who are not reviewers see all.
 */
final readonly class ApplicationReviews
{
    public function __construct(private ReviewRequestRepository $requests, private CompanyMemberAccounts $accounts) {}

    /**
     * The answers given about the application in every review request that names it, newest request first.
     *
     * @return list<ApplicationReviewEntry>
     */
    public function forApplication(int $applicationId, int $viewerId): array
    {
        $loaded = $this->load([$applicationId]);
        $entries = [];
        $names = [];

        foreach ($this->accounts->summaries(array_values(array_unique(array_merge(...array_values($loaded['reviewers'] ?: [[]]))))) as $userId => $summary) {
            $names[$userId] = $summary->name;
        }

        foreach ($loaded['requests'] as $request) {
            $id = (int) $request->id;
            $answers = $loaded['answers'][$id][$applicationId] ?? [];
            $locked = $this->isLocked($viewerId, $loaded['reviewers'][$id] ?? [], $answers);

            foreach ($answers as $answer) {
                if ($locked && $answer->reviewerId !== $viewerId) {
                    continue;
                }

                $entries[] = new ApplicationReviewEntry($id, $request->dueOn, $request->status, $answer->reviewerId, $names[$answer->reviewerId] ?? null, $answer->decision, $answer->reason, $answer->answeredAt);
            }
        }

        return $entries;
    }

    /**
     * The decisions and the answers still awaited about each application over its review requests that are not closed. Where the viewer is a reviewer who has not answered, only their own
     * pending answer counts (the others' stay hidden). An application in `$finalApplicationIds` awaits nothing.
     *
     * @param  list<int>  $applicationIds
     * @param  list<int>  $finalApplicationIds
     * @return array<int, array{advance: int, decline: int, maybe: int, pending: int}> an application in no open request is missing
     */
    public function summaries(array $applicationIds, int $viewerId, array $finalApplicationIds = []): array
    {
        $loaded = $this->load($applicationIds);
        $summaries = [];

        foreach ($loaded['requests'] as $request) {
            $id = (int) $request->id;

            if ($request->status === ReviewRequestStatus::Closed) {
                continue;
            }

            foreach ($loaded['items'][$id] ?? [] as $applicationId) {
                $summary = $summaries[$applicationId] ?? ['advance' => 0, 'decline' => 0, 'maybe' => 0, 'pending' => 0];
                $reviewers = $loaded['reviewers'][$id] ?? [];
                $answers = $loaded['answers'][$id][$applicationId] ?? [];
                $awaits = ! in_array($applicationId, $finalApplicationIds, true);

                if ($this->isLocked($viewerId, $reviewers, $answers)) {
                    $summary['pending'] += $awaits ? 1 : 0;
                } else {
                    foreach ($answers as $answer) {
                        $summary[$answer->decision->value]++;
                    }

                    $summary['pending'] += $awaits ? count(array_diff($reviewers, array_map(static fn (ReviewAnswer $answer): int => $answer->reviewerId, $answers))) : 0;
                }

                $summaries[$applicationId] = $summary;
            }
        }

        return $summaries;
    }

    /**
     * @param  list<ReviewAnswer>  $answers  about one application
     * @param  list<int>  $reviewers
     */
    private function isLocked(int $viewerId, array $reviewers, array $answers): bool
    {
        return in_array($viewerId, $reviewers, true) && array_filter($answers, static fn (ReviewAnswer $answer): bool => $answer->reviewerId === $viewerId) === [];
    }

    /**
     * @param  list<int>  $applicationIds
     * @return array{requests: list<ReviewRequest>, items: array<int, list<int>>, reviewers: array<int, list<int>>, answers: array<int, array<int, list<ReviewAnswer>>>}
     */
    private function load(array $applicationIds): array
    {
        $byApplication = $this->requests->requestIdsForApplications($applicationIds);
        $ids = array_values(array_unique(array_merge(...array_values($byApplication ?: [[]]))));

        if ($ids === []) {
            return ['requests' => [], 'items' => [], 'reviewers' => [], 'answers' => []];
        }

        $answers = [];

        foreach ($this->requests->answers($ids) as $requestId => $given) {
            foreach ($given as $answer) {
                $answers[$requestId][$answer->applicationId][] = $answer;
            }
        }

        $requests = $this->requests->findMany($ids);
        usort($requests, static fn (ReviewRequest $a, ReviewRequest $b): int => ($b->id ?? 0) <=> ($a->id ?? 0));

        return ['requests' => $requests, 'items' => $this->requests->applicationIds($ids), 'reviewers' => $this->requests->reviewerIds($ids), 'answers' => $answers];
    }
}
