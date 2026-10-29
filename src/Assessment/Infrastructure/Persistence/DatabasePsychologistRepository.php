<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\PsychologistRole;
use Src\Assessment\Domain\PsychologistStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabasePsychologistRepository implements PsychologistRepository
{
    private const string TABLE = 'assessment_psychologists';

    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function findById(int $id, bool $forUpdate = false): ?Psychologist
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->toPsychologist($row);
    }

    public function findByUserId(int $userId): ?Psychologist
    {
        $row = $this->connection->table(self::TABLE)->where('user_id', $userId)->first();

        return $row === null ? null : $this->toPsychologist($row);
    }

    public function userIdsOf(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $users = [];

        foreach ($this->connection->table(self::TABLE)->whereIn('id', $ids)->get(['id', 'user_id']) as $row) {
            $users[(int) $row->id] = (int) $row->user_id;
        }

        return $users;
    }

    public function save(Psychologist $psychologist): Psychologist
    {
        $now = $this->clock->now();
        $values = [
            'str_number' => $psychologist->strNumber(),
            'str_valid_until' => $psychologist->strValidUntil()?->format(self::DATE_FORMAT),
            'silp_number' => $psychologist->silpNumber(),
            'silp_valid_until' => $psychologist->silpValidUntil()?->format(self::DATE_FORMAT),
            'status' => $psychologist->status()->value,
            'role' => $psychologist->role()->value,
            'signature_path' => $psychologist->signaturePath(),
            'updated_at' => $now,
        ];

        if ($psychologist->id !== null) {
            $this->connection->table(self::TABLE)->where('id', $psychologist->id)->update($values);

            return $psychologist;
        }

        $id = $this->connection->table(self::TABLE)->insertGetId([
            'user_id' => $psychologist->userId,
            'provider_id' => $psychologist->providerId,
            'created_at' => $now,
            ...$values,
        ]);

        return new Psychologist(
            (int) $id, $psychologist->userId, $psychologist->providerId, $psychologist->strNumber(), $psychologist->strValidUntil(),
            $psychologist->silpNumber(), $psychologist->silpValidUntil(), $psychologist->status(), $psychologist->role(), $now, $psychologist->signaturePath(),
        );
    }

    public function ofProvider(int $providerId): array
    {
        return array_map(
            fn (stdClass $row): Psychologist => $this->toPsychologist($row),
            $this->connection->table(self::TABLE)->where('provider_id', $providerId)->orderBy('id')->get()->all(),
        );
    }

    public function activeLeadCount(int $providerId): int
    {
        return $this->connection->table(self::TABLE)
            ->where('provider_id', $providerId)->where('role', PsychologistRole::Lead->value)->where('status', PsychologistStatus::Active->value)
            ->count();
    }

    public function pageOfProvider(int $providerId, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE)->where('provider_id', $providerId);

        $total = (clone $builder)->count();
        $rows = $builder->orderBy('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map(fn (stdClass $row): Psychologist => $this->toPsychologist($row), $rows->all()), $total, $request);
    }

    private function toPsychologist(stdClass $row): Psychologist
    {
        $utc = new DateTimeZone('UTC');

        return new Psychologist(
            (int) $row->id,
            (int) $row->user_id,
            (int) $row->provider_id,
            $row->str_number === null ? null : (string) $row->str_number,
            $row->str_valid_until === null ? null : new DateTimeImmutable((string) $row->str_valid_until, $utc),
            $row->silp_number === null ? null : (string) $row->silp_number,
            $row->silp_valid_until === null ? null : new DateTimeImmutable((string) $row->silp_valid_until, $utc),
            PsychologistStatus::from($row->status),
            PsychologistRole::from($row->role),
            new DateTimeImmutable((string) $row->created_at, $utc),
            $row->signature_path === null ? null : (string) $row->signature_path,
        );
    }
}
