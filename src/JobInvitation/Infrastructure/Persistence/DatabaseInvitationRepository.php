<?php

namespace Src\JobInvitation\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\JobInvitation\Domain\Invitation;
use Src\JobInvitation\Domain\InvitationRepository;
use Src\JobInvitation\Domain\InvitationStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;
use stdClass;

final readonly class DatabaseInvitationRepository implements InvitationRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(Invitation $invitation): Invitation
    {
        try {
            $id = (int) $this->connection->table('jobinvitation_invitations')->insertGetId([
                'company_id' => $invitation->companyId,
                'job_id' => $invitation->jobId,
                'user_id' => $invitation->userId,
                'invited_by_user_id' => $invitation->invitedByUserId,
                'note' => $invitation->note,
                'status' => $invitation->status->value,
                'created_at' => $this->format($invitation->createdAt),
                'updated_at' => $this->format($invitation->updatedAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('This candidate was already invited to the job.', 'already-invited');
        }

        return new Invitation(
            $invitation->companyId, $invitation->jobId, $invitation->userId, $invitation->invitedByUserId, $invitation->note, $invitation->status, null, null, null, null,
            $invitation->createdAt, $invitation->updatedAt, $id,
        );
    }

    public function save(Invitation $invitation): void
    {
        $this->connection->table('jobinvitation_invitations')->where('id', $invitation->id)->update([
            'status' => $invitation->status->value,
            'decline_reason' => $invitation->declineReason,
            'viewed_at' => $this->formatOrNull($invitation->viewedAt),
            'responded_at' => $this->formatOrNull($invitation->respondedAt),
            'application_id' => $invitation->applicationId,
            'updated_at' => $this->format($invitation->updatedAt),
        ]);
    }

    public function exists(int $jobId, int $userId): bool
    {
        return $this->connection->table('jobinvitation_invitations')->where('job_id', $jobId)->where('user_id', $userId)->exists();
    }

    public function findOfUser(int $userId, int $id, bool $forUpdate = false): ?Invitation
    {
        $query = $this->connection->table('jobinvitation_invitations')->where('id', $id)->where('user_id', $userId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toInvitation($row);
    }

    public function findForJobAndUser(int $jobId, int $userId, bool $forUpdate = false): ?Invitation
    {
        $query = $this->connection->table('jobinvitation_invitations')->where('job_id', $jobId)->where('user_id', $userId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toInvitation($row);
    }

    public function countOfCompanySince(int $companyId, DateTimeImmutable $since): int
    {
        return $this->connection->table('jobinvitation_invitations')->where('company_id', $companyId)->where('created_at', '>=', $this->format($since))->count();
    }

    public function latestOfCompany(int $companyId, array $userIds): array
    {
        $latest = [];

        foreach ($this->connection->table('jobinvitation_invitations')->where('company_id', $companyId)->whereIn('user_id', $userIds)
            ->selectRaw('DISTINCT ON (user_id) *')->orderBy('user_id')->orderByDesc('created_at')->orderByDesc('id')->get() as $row) {
            $latest[(int) $row->user_id] = $this->toInvitation($row);
        }

        return $latest;
    }

    public function ofJob(int $jobId, ?InvitationStatus $status, PageRequest $request): Page
    {
        $query = $this->connection->table('jobinvitation_invitations')->where('job_id', $jobId);

        return $this->page($query, $status, $request);
    }

    public function ofUser(int $userId, ?InvitationStatus $status, PageRequest $request): Page
    {
        $query = $this->connection->table('jobinvitation_invitations')->where('user_id', $userId);

        return $this->page($query, $status, $request);
    }

    public function deleteOfUser(int $userId): void
    {
        $this->connection->table('jobinvitation_invitations')->where('user_id', $userId)->delete();
    }

    /** @return Page<Invitation> */
    private function page(Builder $query, ?InvitationStatus $status, PageRequest $request): Page
    {
        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $total = (clone $query)->count();
        $rows = $total === 0 ? [] : $query->orderByDesc('created_at')->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get()->all();

        return new Page(array_map(fn (stdClass $row): Invitation => $this->toInvitation($row), $rows), $total, $request);
    }

    private function toInvitation(stdClass $row): Invitation
    {
        return new Invitation(
            (int) $row->company_id,
            (int) $row->job_id,
            (int) $row->user_id,
            (int) $row->invited_by_user_id,
            $row->note,
            InvitationStatus::from((string) $row->status),
            $row->decline_reason,
            $this->timeOrNull($row->viewed_at),
            $this->timeOrNull($row->responded_at),
            $row->application_id === null ? null : (int) $row->application_id,
            $this->time((string) $row->created_at),
            $this->time((string) $row->updated_at),
            (int) $row->id,
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }

    private function formatOrNull(?DateTimeImmutable $time): ?string
    {
        return $time === null ? null : $this->format($time);
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function timeOrNull(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : $this->time($value);
    }
}
