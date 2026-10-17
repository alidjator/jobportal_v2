<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\ReviewAnswer;
use Src\Recruitment\Domain\ReviewDecision;
use Src\Recruitment\Domain\ReviewRequest;
use Src\Recruitment\Domain\ReviewRequestRepository;
use Src\Recruitment\Domain\ReviewRequestStatus;
use stdClass;

final readonly class DatabaseReviewRequestRepository implements ReviewRequestRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string ZONE = 'Asia/Jakarta';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(ReviewRequest $request, array $applicationIds, array $reviewerIds): ReviewRequest
    {
        $now = $this->format($request->createdAt);
        $id = (int) $this->connection->table('recruitment_review_requests')->insertGetId([
            'company_id' => $request->companyId,
            'job_id' => $request->jobId,
            'requested_by_user_id' => $request->requestedBy,
            'message' => $request->message,
            'due_on' => $request->dueOn->format('Y-m-d'),
            'status' => $request->status->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->connection->table('recruitment_review_request_items')->insert(array_map(static fn (int $applicationId): array => ['request_id' => $id, 'application_id' => $applicationId], $applicationIds));
        $this->connection->table('recruitment_review_request_reviewers')->insert(array_map(static fn (int $userId): array => ['request_id' => $id, 'user_id' => $userId], $reviewerIds));

        return new ReviewRequest($request->companyId, $request->jobId, $request->requestedBy, $request->message, $request->dueOn, $request->status, $request->createdAt, $id);
    }

    public function find(int $id, bool $forUpdate = false): ?ReviewRequest
    {
        $query = $this->connection->table('recruitment_review_requests')->where('id', $id);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toRequest($row);
    }

    public function forJob(int $jobId): array
    {
        return $this->connection->table('recruitment_review_requests')->where('job_id', $jobId)->orderByDesc('id')->get()
            ->map(fn (stdClass $row): ReviewRequest => $this->toRequest($row))->all();
    }

    public function forReviewer(int $userId, int $companyId): array
    {
        return $this->connection->table('recruitment_review_requests as r')
            ->join('recruitment_review_request_reviewers as v', 'v.request_id', '=', 'r.id')
            ->where('v.user_id', $userId)->where('r.company_id', $companyId)
            ->orderByDesc('r.due_on')->orderByDesc('r.id')
            ->get(['r.*'])
            ->map(fn (stdClass $row): ReviewRequest => $this->toRequest($row))->all();
    }

    public function forRequester(int $userId, int $companyId): array
    {
        return $this->connection->table('recruitment_review_requests')->where('requested_by_user_id', $userId)->where('company_id', $companyId)
            ->orderByDesc('due_on')->orderByDesc('id')->get()
            ->map(fn (stdClass $row): ReviewRequest => $this->toRequest($row))->all();
    }

    public function applicationIds(array $requestIds): array
    {
        $ids = [];

        foreach ($this->connection->table('recruitment_review_request_items')->whereIn('request_id', $requestIds)->orderBy('id')->get(['request_id', 'application_id']) as $row) {
            $ids[(int) $row->request_id][] = (int) $row->application_id;
        }

        return $ids;
    }

    public function reviewerIds(array $requestIds): array
    {
        $ids = [];

        foreach ($this->connection->table('recruitment_review_request_reviewers')->whereIn('request_id', $requestIds)->orderBy('id')->get(['request_id', 'user_id']) as $row) {
            $ids[(int) $row->request_id][] = (int) $row->user_id;
        }

        return $ids;
    }

    public function answers(array $requestIds): array
    {
        $answers = [];

        foreach ($this->connection->table('recruitment_application_reviews')->whereIn('request_id', $requestIds)->orderBy('id')->get() as $row) {
            $answers[(int) $row->request_id][] = new ReviewAnswer(
                (int) $row->request_id,
                (int) $row->application_id,
                (int) $row->reviewer_user_id,
                ReviewDecision::from((string) $row->decision),
                $row->reason === null ? null : (string) $row->reason,
                $this->time((string) $row->answered_at),
            );
        }

        return $answers;
    }

    public function saveAnswer(ReviewAnswer $answer): void
    {
        $at = $this->format($answer->answeredAt);

        $this->connection->table('recruitment_application_reviews')->upsert(
            [[
                'request_id' => $answer->requestId,
                'application_id' => $answer->applicationId,
                'reviewer_user_id' => $answer->reviewerId,
                'decision' => $answer->decision->value,
                'reason' => $answer->reason,
                'answered_at' => $at,
                'updated_at' => $at,
            ]],
            ['request_id', 'application_id', 'reviewer_user_id'],
            ['decision', 'reason', 'updated_at'],
        );
    }

    public function setStatus(int $id, ReviewRequestStatus $status, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_review_requests')->where('id', $id)->update(['status' => $status->value, 'updated_at' => $this->format($at)]);
    }

    public function findMany(array $ids): array
    {
        return $this->connection->table('recruitment_review_requests')->whereIn('id', $ids)->orderBy('id')->get()
            ->map(fn (stdClass $row): ReviewRequest => $this->toRequest($row))->all();
    }

    public function requestIdsForApplications(array $applicationIds): array
    {
        $ids = [];

        foreach ($this->connection->table('recruitment_review_request_items')->whereIn('application_id', $applicationIds)->orderBy('request_id')->get(['request_id', 'application_id']) as $row) {
            $ids[(int) $row->application_id][] = (int) $row->request_id;
        }

        return $ids;
    }

    public function dueForReminder(DateTimeImmutable $firstDue, DateTimeImmutable $lastDue): array
    {
        return $this->connection->table('recruitment_review_requests')
            ->where('status', ReviewRequestStatus::Open->value)->whereNull('reminded_at')
            ->where('due_on', '>=', $firstDue->format('Y-m-d'))->where('due_on', '<=', $lastDue->format('Y-m-d'))
            ->orderBy('id')->get()->map(fn (stdClass $row): ReviewRequest => $this->toRequest($row))->all();
    }

    public function claimReminder(int $id, DateTimeImmutable $at): bool
    {
        return $this->connection->table('recruitment_review_requests')->where('id', $id)->whereNull('reminded_at')->update(['reminded_at' => $this->format($at)]) > 0;
    }

    public function dueForOverdueNotice(DateTimeImmutable $today): array
    {
        return $this->connection->table('recruitment_review_requests')
            ->where('status', ReviewRequestStatus::Open->value)->whereNull('overdue_notified_at')->where('due_on', '<', $today->format('Y-m-d'))
            ->orderBy('id')->get()->map(fn (stdClass $row): ReviewRequest => $this->toRequest($row))->all();
    }

    public function claimOverdueNotice(int $id, DateTimeImmutable $at): bool
    {
        return $this->connection->table('recruitment_review_requests')->where('id', $id)->whereNull('overdue_notified_at')->update(['overdue_notified_at' => $this->format($at)]) > 0;
    }

    private function toRequest(stdClass $row): ReviewRequest
    {
        return new ReviewRequest(
            (int) $row->company_id,
            (int) $row->job_id,
            (int) $row->requested_by_user_id,
            $row->message === null ? null : (string) $row->message,
            new DateTimeImmutable((string) $row->due_on, new DateTimeZone(self::ZONE)),
            ReviewRequestStatus::from((string) $row->status),
            $this->time((string) $row->created_at),
            (int) $row->id,
            $row->reminded_at === null ? null : $this->time((string) $row->reminded_at),
            $row->overdue_notified_at === null ? null : $this->time((string) $row->overdue_notified_at),
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
