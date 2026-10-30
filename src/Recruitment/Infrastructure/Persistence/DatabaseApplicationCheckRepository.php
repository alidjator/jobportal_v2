<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\CheckKind;
use Src\Recruitment\Domain\CheckOutcome;
use Src\Recruitment\Domain\CheckStatus;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Domain\Exceptions\StateConflict;
use stdClass;

final readonly class DatabaseApplicationCheckRepository implements ApplicationCheckRepository
{
    private const string TABLE = 'recruitment_application_checks';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(ApplicationCheck $check): ApplicationCheck
    {
        try {
            $id = (int) $this->connection->table(self::TABLE)->insertGetId([
                'application_id' => $check->applicationId,
                'check_type_id' => $check->checkTypeId,
                'status' => $check->status->value,
                'consent_required' => $check->consentRequired,
                'scheduled_at' => $this->formatOrNull($check->scheduledAt),
                'place' => $check->place,
                'vendor' => $check->vendor,
                'note_to_candidate' => $check->noteToCandidate,
                'requested_by' => $check->requestedBy,
                'created_at' => $this->format($check->createdAt),
                'updated_at' => $this->format($check->updatedAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('This check is already open for the application.', 'check-already-open');
        }

        return $this->find($check->applicationId, $id) ?? throw new \LogicException('The new check was not found.');
    }

    public function save(ApplicationCheck $check): void
    {
        $this->connection->table(self::TABLE)->where('application_id', $check->applicationId)->where('id', $check->id)->update([
            'status' => $check->status->value,
            'scheduled_at' => $this->formatOrNull($check->scheduledAt),
            'place' => $check->place,
            'vendor' => $check->vendor,
            'note_to_candidate' => $check->noteToCandidate,
            'consented_at' => $this->formatOrNull($check->consentedAt),
            'consent_refused_at' => $this->formatOrNull($check->consentRefusedAt),
            'refusal_reason' => $check->refusalReason,
            'outcome' => $check->outcome?->value,
            'result_note' => $check->resultNote,
            'result_file_path' => $check->resultFilePath,
            'result_file_name' => $check->resultFileName,
            'result_recorded_by' => $check->resultRecordedBy,
            'result_recorded_at' => $this->formatOrNull($check->resultRecordedAt),
            'updated_at' => $this->format($check->updatedAt),
        ]);
    }

    public function find(int $applicationId, int $id, bool $forUpdate = false): ?ApplicationCheck
    {
        $query = $this->connection->table(self::TABLE)->where('application_id', $applicationId)->where('id', $id);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toCheck($row);
    }

    public function ofApplication(int $applicationId): array
    {
        return array_map(
            fn (stdClass $row): ApplicationCheck => $this->toCheck($row),
            $this->connection->table(self::TABLE)->where('application_id', $applicationId)->orderBy('id')->get()->all(),
        );
    }

    public function dueForPurge(DateTimeImmutable $finalBefore, int $limit): array
    {
        $rows = $this->connection->table(self::TABLE.' as c')
            ->join('recruitment_applications as a', 'a.id', '=', 'c.application_id')
            ->join('recruitment_check_types as t', 't.id', '=', 'c.check_type_id')
            ->whereIn('a.stage_type', [StageType::Rejected->value, StageType::Withdrawn->value, StageType::Hired->value])
            ->where('a.stage_entered_at', '<=', $this->format($finalBefore))
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $medical) => $medical->where('t.kind', CheckKind::Medical->value)->where(fn (Builder $held) => $held->whereNotNull('c.outcome')->orWhereNotNull('c.result_note')->orWhereNotNull('c.result_file_path')))
                ->orWhere(fn (Builder $other) => $other->where('t.kind', '!=', CheckKind::Medical->value)->whereNotNull('c.result_file_path')))
            ->orderBy('c.id')
            ->limit($limit)
            ->get(['c.*']);

        return array_map(fn (stdClass $row): ApplicationCheck => $this->toCheck($row), $rows->all());
    }

    private function toCheck(stdClass $row): ApplicationCheck
    {
        return new ApplicationCheck(
            (int) $row->application_id,
            (int) $row->check_type_id,
            CheckStatus::from((string) $row->status),
            (bool) $row->consent_required,
            $this->timeOrNull($row->scheduled_at),
            $row->place,
            $row->vendor,
            $row->note_to_candidate,
            $this->timeOrNull($row->consented_at),
            $this->timeOrNull($row->consent_refused_at),
            $row->refusal_reason,
            $row->outcome === null ? null : CheckOutcome::from((string) $row->outcome),
            $row->result_note,
            $row->result_file_path,
            $row->result_file_name,
            $row->result_recorded_by === null ? null : (int) $row->result_recorded_by,
            $this->timeOrNull($row->result_recorded_at),
            (int) $row->requested_by,
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
