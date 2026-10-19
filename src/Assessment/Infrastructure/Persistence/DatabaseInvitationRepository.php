<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use LogicException;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationAttachment;
use Src\Assessment\Domain\InvitationMode;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\InvitationStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseInvitationRepository implements InvitationRepository
{
    private const string TABLE = 'assessment_invitations';

    private const string ASSESSORS = 'assessment_invitation_assessors';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function find(int $id, bool $forUpdate = false): ?Invitation
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->hydrate([$row])[0];
    }

    public function active(int $applicationId, bool $forUpdate = false): ?Invitation
    {
        $query = $this->connection->table(self::TABLE)->where('application_id', $applicationId)->whereIn('status', $this->activeStatuses());
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->hydrate([$row])[0];
    }

    public function activeApplicationIds(int $companyId, ?array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        return $this->connection->table(self::TABLE)
            ->where('company_id', $companyId)
            ->whereIn('status', $this->activeStatuses())
            ->when($jobIds !== null, fn ($query) => $query->whereIn('job_id', $jobIds))
            ->pluck('application_id')
            ->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    public function add(Invitation $invitation): Invitation
    {
        $now = $this->clock->now();

        $id = (int) $this->connection->table(self::TABLE)->insertGetId([
            'application_id' => $invitation->applicationId,
            'company_id' => $invitation->companyId,
            'job_id' => $invitation->jobId,
            'applicant_user_id' => $invitation->applicantUserId,
            'test_id' => $invitation->testId,
            'invited_by_user_id' => $invitation->invitedByUserId,
            'starts_at' => $this->format($invitation->startsAt),
            'due_at' => $this->format($invitation->dueAt),
            'status' => $invitation->status->value,
            'mode' => $invitation->mode->value,
            'location' => $invitation->location,
            'note' => $invitation->note,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($invitation->assessorIds as $userId) {
            $this->connection->table(self::ASSESSORS)->insert(['invitation_id' => $id, 'user_id' => $userId]);
        }

        return $this->find($id) ?? throw new LogicException('The new invitation was not found.');
    }

    public function update(Invitation $invitation): void
    {
        $this->connection->table(self::TABLE)->where('id', $invitation->id)->update([
            'status' => $invitation->status->value,
            'reminded_at' => $invitation->remindedAt === null ? null : $this->format($invitation->remindedAt),
            'closed_at' => $invitation->closedAt === null ? null : $this->format($invitation->closedAt),
            'attachment_path' => $invitation->attachment?->path,
            'attachment_name' => $invitation->attachment?->name,
            'attachment_mime' => $invitation->attachment?->mimeType,
            'attachment_size_bytes' => $invitation->attachment?->sizeBytes,
            'updated_at' => $this->clock->now(),
        ]);
    }

    public function ofApplication(int $applicationId): array
    {
        $rows = $this->connection->table(self::TABLE)->where('application_id', $applicationId)->orderByDesc('id')->get()->all();

        return $this->hydrate($rows);
    }

    public function pageOfApplicant(int $userId, ?InvitationStatus $status, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE)->where('applicant_user_id', $userId);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        $total = (clone $builder)->count();
        $rows = $builder->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get()->all();

        return new Page($this->hydrate($rows), $total, $request);
    }

    public function dueForReminder(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $rows = $this->connection->table(self::TABLE)
            ->where('status', InvitationStatus::Invited->value)
            ->whereNull('reminded_at')
            ->where('due_at', '>', $this->format($from))
            ->where('due_at', '<=', $this->format($until))
            ->orderBy('due_at')->orderBy('id')
            ->get()->all();

        return $this->hydrate($rows);
    }

    public function dueForExpiry(DateTimeImmutable $now): array
    {
        $rows = $this->connection->table(self::TABLE)
            ->where('status', InvitationStatus::Invited->value)
            ->where('due_at', '<=', $this->format($now))
            ->orderBy('due_at')->orderBy('id')
            ->get()->all();

        return $this->hydrate($rows);
    }

    public function existsForTest(int $testId): bool
    {
        return $this->connection->table(self::TABLE)->where('test_id', $testId)->exists();
    }

    /** @return list<string> */
    private function activeStatuses(): array
    {
        return array_values(array_map(static fn (InvitationStatus $status): string => $status->value, array_filter(InvitationStatus::cases(), static fn (InvitationStatus $status): bool => $status->isActive())));
    }

    /**
     * The invitations of these rows, with their assessors read in one query.
     *
     * @param  array<int, stdClass>  $rows
     * @return list<Invitation>
     */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $assessors = [];

        foreach ($this->connection->table(self::ASSESSORS)->whereIn('invitation_id', array_map(static fn (stdClass $row): int => (int) $row->id, $rows))->orderBy('id')->get() as $assessor) {
            $assessors[(int) $assessor->invitation_id][] = (int) $assessor->user_id;
        }

        return array_values(array_map(fn (stdClass $row): Invitation => new Invitation(
            (int) $row->id,
            (int) $row->application_id,
            (int) $row->company_id,
            (int) $row->job_id,
            (int) $row->applicant_user_id,
            (int) $row->test_id,
            $this->time((string) $row->starts_at),
            $this->time((string) $row->due_at),
            InvitationStatus::from((string) $row->status),
            (int) $row->invited_by_user_id,
            $assessors[(int) $row->id] ?? [],
            $row->reminded_at === null ? null : $this->time((string) $row->reminded_at),
            $row->closed_at === null ? null : $this->time((string) $row->closed_at),
            $this->time((string) $row->created_at),
            InvitationMode::from((string) $row->mode),
            $row->location,
            $row->attachment_path === null ? null : new InvitationAttachment((string) $row->attachment_path, (string) $row->attachment_name, (string) $row->attachment_mime, (int) $row->attachment_size_bytes),
            $row->note,
        ), $rows));
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
