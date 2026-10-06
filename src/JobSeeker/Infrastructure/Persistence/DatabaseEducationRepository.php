<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\Education;
use Src\JobSeeker\Domain\EducationRepository;
use stdClass;

final readonly class DatabaseEducationRepository implements EducationRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function forUser(int $userId): array
    {
        return $this->connection->table('jobseeker_educations')
            ->where('user_id', $userId)
            ->orderByDesc('is_current')
            ->orderByRaw('end_year is null desc')
            ->orderByDesc('end_year')
            ->orderByDesc('start_year')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (stdClass $row): Education => self::hydrate($row))
            ->all();
    }

    public function find(int $userId, int $id): ?Education
    {
        $row = $this->connection->table('jobseeker_educations')->where('user_id', $userId)->where('id', $id)->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function count(int $userId): int
    {
        return $this->connection->table('jobseeker_educations')->where('user_id', $userId)->count();
    }

    public function add(Education $education, DateTimeImmutable $at): int
    {
        $time = $at->format(self::TIME_FORMAT);

        return (int) $this->connection->table('jobseeker_educations')->insertGetId([...$this->columns($education), 'user_id' => $education->userId, 'created_at' => $time, 'updated_at' => $time]);
    }

    public function save(Education $education, DateTimeImmutable $at): void
    {
        $this->connection->table('jobseeker_educations')
            ->where('user_id', $education->userId)
            ->where('id', $education->id)
            ->update([...$this->columns($education), 'updated_at' => $at->format(self::TIME_FORMAT)]);
    }

    public function delete(int $userId, int $id): void
    {
        $this->connection->table('jobseeker_educations')->where('user_id', $userId)->where('id', $id)->delete();
    }

    /** @return array<string, mixed> */
    private function columns(Education $education): array
    {
        return [
            'education_level_id' => $education->educationLevelId,
            'institution' => $education->institution,
            'field_of_study' => $education->fieldOfStudy,
            'start_year' => $education->startYear,
            'end_year' => $education->endYear,
            'is_current' => $education->isCurrent,
            'gpa' => $education->gpa,
            'description' => $education->description,
        ];
    }

    private static function hydrate(stdClass $row): Education
    {
        return new Education(
            (int) $row->user_id, (int) $row->education_level_id, $row->institution, $row->field_of_study, (int) $row->start_year,
            $row->end_year === null ? null : (int) $row->end_year, (bool) $row->is_current, $row->gpa === null ? null : number_format((float) $row->gpa, 2, '.', ''),
            $row->description, (int) $row->id,
        );
    }
}
