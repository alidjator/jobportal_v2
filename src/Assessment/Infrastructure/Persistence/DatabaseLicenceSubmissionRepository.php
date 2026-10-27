<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\LicenceKind;
use Src\Assessment\Domain\LicenceQueueRow;
use Src\Assessment\Domain\LicenceStatus;
use Src\Assessment\Domain\LicenceSubmission;
use Src\Assessment\Domain\LicenceSubmissionRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseLicenceSubmissionRepository implements LicenceSubmissionRepository
{
    private const string TABLE = 'assessment_licence_submissions';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function save(LicenceSubmission $submission): LicenceSubmission
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        if ($submission->id !== null) {
            $this->connection->table(self::TABLE)->where('id', $submission->id)->update([
                'status' => $submission->status()->value,
                'decided_by_admin_id' => $submission->decidedByAdminId(),
                'decided_at' => $submission->decidedAt()?->format(self::TIME_FORMAT),
                'decision_note' => $submission->decisionNote(),
                'updated_at' => $now,
            ]);

            return $submission;
        }

        $id = $this->connection->table(self::TABLE)->insertGetId([
            'psychologist_id' => $submission->psychologistId,
            'kind' => $submission->kind->value,
            'number' => $submission->number,
            'valid_until' => $submission->validUntil->format(self::DATE_FORMAT),
            'document_path' => $submission->documentPath,
            'document_name' => $submission->documentName,
            'document_size' => $submission->documentSize,
            'status' => $submission->status()->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new LicenceSubmission(
            (int) $id, $submission->psychologistId, $submission->kind, $submission->number, $submission->validUntil, $submission->documentPath,
            $submission->documentName, $submission->documentSize, $submission->status(), null, null, null, $this->time($now),
        );
    }

    public function find(int $id, bool $forUpdate = false): ?LicenceSubmission
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toSubmission($row);
    }

    public function ofPsychologist(int $psychologistId): array
    {
        return array_map(
            fn (stdClass $row): LicenceSubmission => $this->toSubmission($row),
            $this->connection->table(self::TABLE)->where('psychologist_id', $psychologistId)->orderByDesc('created_at')->orderByDesc('id')->get()->all(),
        );
    }

    public function hasPending(int $psychologistId, LicenceKind $kind): bool
    {
        return $this->connection->table(self::TABLE)
            ->where('psychologist_id', $psychologistId)->where('kind', $kind->value)->where('status', LicenceStatus::Pending->value)
            ->exists();
    }

    public function queue(?LicenceStatus $status, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE.' as s')
            ->join('assessment_psychologists as p', 'p.id', '=', 's.psychologist_id')
            ->join('assessment_providers as v', 'v.id', '=', 'p.provider_id');

        if ($status !== null) {
            $builder->where('s.status', $status->value);
        }

        $total = (clone $builder)->count();
        $rows = $builder
            ->selectRaw('s.*, p.user_id as psychologist_user_id, p.provider_id as provider_id, v.name as provider_name')
            ->orderByRaw("CASE WHEN s.status = 'pending' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN s.status = 'pending' THEN s.created_at END ASC")
            ->orderByDesc('s.decided_at')
            ->orderByDesc('s.id')
            ->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map(fn (stdClass $row): LicenceQueueRow => new LicenceQueueRow(
            $this->toSubmission($row), (int) $row->psychologist_user_id, (int) $row->provider_id, (string) $row->provider_name,
        ), $rows->all()), $total, $request);
    }

    public function pendingCount(): int
    {
        return $this->connection->table(self::TABLE)->where('status', LicenceStatus::Pending->value)->count();
    }

    private function toSubmission(stdClass $row): LicenceSubmission
    {
        return new LicenceSubmission(
            (int) $row->id,
            (int) $row->psychologist_id,
            LicenceKind::from($row->kind),
            (string) $row->number,
            $this->time((string) $row->valid_until),
            (string) $row->document_path,
            (string) $row->document_name,
            (int) $row->document_size,
            LicenceStatus::from($row->status),
            $row->decided_by_admin_id === null ? null : (int) $row->decided_by_admin_id,
            $row->decided_at === null ? null : $this->time((string) $row->decided_at),
            $row->decision_note === null ? null : (string) $row->decision_note,
            $this->time((string) $row->created_at),
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
