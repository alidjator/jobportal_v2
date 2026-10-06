<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\ExperienceRepository;
use Src\JobSeeker\Domain\WorkExperience;
use stdClass;

final readonly class DatabaseExperienceRepository implements ExperienceRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(private ConnectionInterface $connection) {}

    public function forUser(int $userId): array
    {
        return $this->connection->table('jobseeker_experiences')
            ->where('user_id', $userId)
            ->orderByDesc('is_current')
            ->orderByRaw('end_date is null desc')
            ->orderByDesc('end_date')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (stdClass $row): WorkExperience => self::hydrate($row))
            ->all();
    }

    public function find(int $userId, int $id): ?WorkExperience
    {
        $row = $this->connection->table('jobseeker_experiences')->where('user_id', $userId)->where('id', $id)->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function count(int $userId): int
    {
        return $this->connection->table('jobseeker_experiences')->where('user_id', $userId)->count();
    }

    public function add(WorkExperience $experience, DateTimeImmutable $at): int
    {
        $time = $at->format(self::TIME_FORMAT);

        return (int) $this->connection->table('jobseeker_experiences')->insertGetId([...$this->columns($experience), 'user_id' => $experience->userId, 'created_at' => $time, 'updated_at' => $time]);
    }

    public function save(WorkExperience $experience, DateTimeImmutable $at): void
    {
        $this->connection->table('jobseeker_experiences')
            ->where('user_id', $experience->userId)
            ->where('id', $experience->id)
            ->update([...$this->columns($experience), 'updated_at' => $at->format(self::TIME_FORMAT)]);
    }

    public function delete(int $userId, int $id): void
    {
        $this->connection->table('jobseeker_experiences')->where('user_id', $userId)->where('id', $id)->delete();
    }

    /** @return array<string, mixed> */
    private function columns(WorkExperience $experience): array
    {
        return [
            'job_title' => $experience->jobTitle,
            'company_name' => $experience->companyName,
            'job_type_id' => $experience->jobTypeId,
            'location_text' => $experience->locationText,
            'start_date' => $experience->startDate->format(self::DATE_FORMAT),
            'end_date' => $experience->endDate?->format(self::DATE_FORMAT),
            'is_current' => $experience->isCurrent,
            'description' => $experience->description,
        ];
    }

    private static function hydrate(stdClass $row): WorkExperience
    {
        $utc = new DateTimeZone('UTC');

        return new WorkExperience(
            (int) $row->user_id, $row->job_title, $row->company_name, $row->job_type_id === null ? null : (int) $row->job_type_id, $row->location_text,
            new DateTimeImmutable($row->start_date, $utc), $row->end_date === null ? null : new DateTimeImmutable($row->end_date, $utc), (bool) $row->is_current,
            $row->description, (int) $row->id,
        );
    }
}
