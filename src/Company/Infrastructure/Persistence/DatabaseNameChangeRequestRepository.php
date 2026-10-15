<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Company\Domain\NameChangeRequest;
use Src\Company\Domain\NameChangeRequestRepository;
use Src\Company\Domain\NameChangeStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseNameChangeRequestRepository implements NameChangeRequestRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function find(int $id): ?NameChangeRequest
    {
        $row = $this->connection->table('company_name_change_requests')->where('id', $id)->first();

        return $row === null ? null : $this->toRequest($row);
    }

    public function pendingOf(int $companyId): ?NameChangeRequest
    {
        $row = $this->connection->table('company_name_change_requests')->where('company_id', $companyId)->where('status', NameChangeStatus::Pending->value)->first();

        return $row === null ? null : $this->toRequest($row);
    }

    public function create(NameChangeRequest $request): NameChangeRequest
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        $id = (int) $this->connection->table('company_name_change_requests')->insertGetId([
            ...$this->columns($request),
            'company_id' => $request->companyId,
            'requested_by_user_id' => $request->requestedByUserId,
            'current_name' => $request->currentName,
            'new_name' => $request->newName,
            'reason' => $request->reason,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find($id) ?? throw new \LogicException("Name change request {$id} was just created.");
    }

    public function save(NameChangeRequest $request): void
    {
        $this->connection->table('company_name_change_requests')->where('id', $request->id)->update([
            ...$this->columns($request),
            'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
        ]);
    }

    public function ofCompany(int $companyId, int $limit): array
    {
        return array_map(
            fn (stdClass $row): NameChangeRequest => $this->toRequest($row),
            $this->connection->table('company_name_change_requests')->where('company_id', $companyId)->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()->all(),
        );
    }

    public function page(?NameChangeStatus $status, PageRequest $request): Page
    {
        $query = $this->connection->table('company_name_change_requests');

        if ($status instanceof NameChangeStatus) {
            $query->where('status', $status->value);
        }

        $total = (clone $query)->count();

        // The pending ones first, the oldest first: they have waited longest. The decided ones follow, the newest first.
        $rows = $query
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByRaw("case when status = 'pending' then created_at end asc")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset($request->offset())
            ->limit($request->perPage)
            ->get()
            ->all();

        return new Page(array_map(fn (stdClass $row): NameChangeRequest => $this->toRequest($row), $rows), $total, $request);
    }

    public function withDocumentDecidedBefore(DateTimeImmutable $before, int $limit): array
    {
        return array_map(
            fn (stdClass $row): NameChangeRequest => $this->toRequest($row),
            $this->connection->table('company_name_change_requests')
                ->whereNotNull('document_path')
                ->whereNotNull('decided_at')
                ->where('decided_at', '<', $before->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT))
                ->orderBy('decided_at')->orderBy('id')
                ->limit($limit)
                ->get()
                ->all(),
        );
    }

    public function clearDocument(int $id): void
    {
        $this->connection->table('company_name_change_requests')->where('id', $id)->update(['document_path' => null, 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    /** @return array<string, mixed> */
    private function columns(NameChangeRequest $request): array
    {
        return [
            'document_path' => $request->documentPath,
            'document_name' => $request->documentName,
            'status' => $request->status->value,
            'decided_by_admin_id' => $request->decidedByAdminId,
            'decided_at' => $request->decidedAt?->format(self::TIME_FORMAT),
            'decision_note' => $request->decisionNote,
        ];
    }

    private function toRequest(stdClass $row): NameChangeRequest
    {
        return new NameChangeRequest(
            (int) $row->id,
            (int) $row->company_id,
            (int) $row->requested_by_user_id,
            (string) $row->current_name,
            (string) $row->new_name,
            (string) $row->reason,
            $row->document_path === null ? null : (string) $row->document_path,
            $row->document_name === null ? null : (string) $row->document_name,
            NameChangeStatus::from((string) $row->status),
            $row->decided_by_admin_id === null ? null : (int) $row->decided_by_admin_id,
            $row->decided_at === null ? null : $this->time((string) $row->decided_at),
            $row->decision_note === null ? null : (string) $row->decision_note,
            $row->created_at === null ? null : $this->time((string) $row->created_at),
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
