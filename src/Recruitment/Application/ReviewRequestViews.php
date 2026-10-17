<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\ReviewAnswer;
use Src\Recruitment\Domain\ReviewRequest;
use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;

/**
 * Builds the views of review requests for one viewer (`docs/design/phase-8-hiring-managers.md` section 3). The lock: a reviewer sees the answers of the other reviewers about an
 * application only after sending their own; the requester and members who are not among the reviewers see everything. An application that became final needs no answer.
 */
final readonly class ReviewRequestViews
{
    public function __construct(
        private ReviewRequestRepository $requests,
        private ApplicationRepository $applications,
        private StageRepository $stages,
        private JobDirectory $jobs,
        private CompanyMemberAccounts $accounts,
        private RecruiterApplicationReader $reader,
        private InterviewResults $results,
        private Clock $clock,
    ) {}

    /**
     * @param  list<ReviewRequest>  $requests  of one company
     * @return list<ReviewRequestView>
     */
    public function of(array $requests, int $viewerId, bool $withCards = false): array
    {
        if ($requests === []) {
            return [];
        }

        $ids = array_values(array_filter(array_map(static fn (ReviewRequest $request): ?int => $request->id, $requests)));
        $itemIds = $this->requests->applicationIds($ids);
        $reviewerIds = $this->requests->reviewerIds($ids);
        $answers = $this->requests->answers($ids);
        $today = $this->today();

        $applicationIds = array_values(array_unique(array_merge(...array_values($itemIds ?: [[]]))));
        $loaded = [];

        foreach ($applicationIds as $applicationId) {
            $loaded[$applicationId] = $this->applications->find($applicationId);
        }

        $stageNames = [];

        foreach ($this->stages->forCompany($requests[0]->companyId) as $stage) {
            $stageNames[(int) $stage->id] = $stage->name;
        }

        $userIds = array_map(static fn (ReviewRequest $request): int => $request->requestedBy, $requests);

        foreach ($reviewerIds as $members) {
            $userIds = [...$userIds, ...$members];
        }

        $names = [];

        foreach ($this->accounts->summaries(array_values(array_unique($userIds))) as $userId => $summary) {
            $names[$userId] = $summary->name;
        }

        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (ReviewRequest $request): int => $request->jobId, $requests))));
        $interviewResults = $withCards ? $this->results->latestForApplications($applicationIds, $viewerId) : [];

        return array_map(function (ReviewRequest $request) use ($viewerId, $withCards, $itemIds, $reviewerIds, $answers, $loaded, $stageNames, $names, $titles, $interviewResults, $today): ReviewRequestView {
            $id = (int) $request->id;
            $reviewers = $reviewerIds[$id] ?? [];
            $isReviewer = in_array($viewerId, $reviewers, true);
            $given = [];

            foreach ($answers[$id] ?? [] as $answer) {
                $given[$answer->applicationId][$answer->reviewerId] = $answer;
            }

            $items = [];
            $total = 0;
            $answered = 0;
            $pending = 0;

            foreach ($itemIds[$id] ?? [] as $applicationId) {
                $application = $loaded[$applicationId] ?? null;
                $active = $application instanceof Application && ! $application->stageType->isTerminal() && $application->stageType !== StageType::Hired;
                $mine = $given[$applicationId][$viewerId] ?? null;
                $locked = $isReviewer && ! $mine instanceof ReviewAnswer;

                if ($active) {
                    $total += count($reviewers);
                    $answered += count(array_intersect($reviewers, array_keys($given[$applicationId] ?? [])));
                    $pending += $isReviewer && ! $mine instanceof ReviewAnswer ? 1 : 0;
                }

                $items[] = new ReviewItemView(
                    $applicationId,
                    $application instanceof Application ? ($application->profileSnapshot['name'] ?? null) : null,
                    $application instanceof Application ? $application->stageId : 0,
                    $application instanceof Application ? $application->stageType : StageType::Applied,
                    $application instanceof Application ? ($stageNames[$application->stageId] ?? null) : null,
                    $active,
                    $locked,
                    array_map(fn (int $reviewerId): ReviewAnswerView => ReviewAnswerView::of($reviewerId, $names[$reviewerId] ?? null, $reviewerId === $viewerId, $given[$applicationId][$reviewerId] ?? null, $locked && $reviewerId !== $viewerId), $reviewers),
                    $withCards ? $this->card($applicationId, $interviewResults[$applicationId] ?? null) : null,
                );
            }

            return new ReviewRequestView(
                $request,
                $titles[$request->jobId] ?? null,
                $names[$request->requestedBy] ?? null,
                $request->isOverdue($today),
                array_map(static fn (int $userId): array => ['id' => $userId, 'name' => $names[$userId] ?? null], $reviewers),
                $items,
                ['answered' => $answered, 'total' => $total],
                $isReviewer ? $pending : null,
            );
        }, $requests);
    }

    /** Today's date in Jakarta time, as midnight of that day. */
    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->clock->now()->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d'), new DateTimeZone('Asia/Jakarta'));
    }

    /**
     * What a reviewer needs to decide about a candidate at a glance.
     *
     * @return array<string, mixed>
     */
    private function card(int $applicationId, ?InterviewResult $result): array
    {
        $detail = $this->reader->find($applicationId);
        $snapshot = $detail?->snapshot;
        $experience = $snapshot['experiences'][0] ?? null;
        $education = $snapshot['educations'][0] ?? null;
        $salary = $snapshot['expected_salary'] ?? null;

        return [
            'name' => $snapshot['name'] ?? null,
            'headline' => $snapshot['headline'] ?? null,
            'city' => $snapshot['location']['city'] ?? null,
            'experience_level' => $snapshot['experience_level']['name'] ?? ($snapshot['experience_level'] ?? null),
            'latest_experience' => is_array($experience) ? ['title' => (string) $experience['job_title'], 'company' => (string) $experience['company_name']] : null,
            'education' => is_array($education) ? ['level' => $education['level']['name'] ?? ($education['level'] ?? null), 'institution' => $education['institution'] ?? null, 'field_of_study' => $education['field_of_study'] ?? null] : null,
            'expected_salary' => is_array($salary) ? ['amount' => (int) $salary['amount'], 'period' => $salary['period']['name'] ?? ($salary['period'] ?? null)] : null,
            'meets_must_have' => $detail?->meetsMustHave,
            'screening_answers' => array_map(fn (ApplicationAnswerView $answer): array => ['question' => $answer->questionText, 'answer' => $this->answerText($answer)], $detail === null ? [] : $detail->answers),
            'interview_result' => $result === null ? null : ['score' => $result->score, 'yes' => $result->yes(), 'no' => $result->no()],
            'has_resume' => $detail !== null && $detail->resumeName !== null,
        ];
    }

    private function answerText(ApplicationAnswerView $answer): ?string
    {
        if ($answer->options !== []) {
            return implode(', ', array_map(static fn (array $option): string => $option['label'], $answer->options));
        }

        return $answer->text ?? ($answer->number !== null ? (string) $answer->number : ($answer->bool === null ? null : ($answer->bool ? 'yes' : 'no')));
    }
}
