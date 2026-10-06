<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\Availability;
use Src\JobSeeker\Domain\Gender;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\MaritalStatus;
use Src\JobSeeker\Domain\ProfileRepository;
use stdClass;

final readonly class DatabaseProfileRepository implements ProfileRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $userId): ?JobSeekerProfile
    {
        $row = $this->connection->table('jobseeker_profiles')->where('user_id', $userId)->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function save(JobSeekerProfile $profile, DateTimeImmutable $at): void
    {
        $time = $at->format(self::TIME_FORMAT);

        $this->connection->table('jobseeker_profiles')->upsert(
            [[
                'user_id' => $profile->userId,
                'phone' => $profile->phone,
                'whatsapp' => $profile->whatsapp,
                'birth_date' => $profile->birthDate?->format(self::DATE_FORMAT),
                'gender' => $profile->gender?->value,
                'marital_status' => $profile->maritalStatus?->value,
                'religion_id' => $profile->religionId,
                'nationality_id' => $profile->nationalityId,
                'expected_salary' => $profile->expectedSalary,
                'expected_salary_period_id' => $profile->expectedSalaryPeriodId,
                'experience_level_id' => $profile->experienceLevelId,
                'availability' => $profile->availability?->value,
                'available_from' => $profile->availableFrom?->format(self::DATE_FORMAT),
                'province_id' => $profile->provinceId,
                'city_id' => $profile->cityId,
                'subdistrict_id' => $profile->subdistrictId,
                'village_id' => $profile->villageId,
                'postal_code_id' => $profile->postalCodeId,
                'address_line' => $profile->addressLine,
                'headline' => $profile->headline,
                'summary' => $profile->summary,
                'is_discoverable' => $profile->isDiscoverable,
                'resume_discoverable' => $profile->resumeDiscoverable,
                'created_at' => $time,
                'updated_at' => $time,
            ]],
            ['user_id'],
            [
                'phone', 'whatsapp', 'birth_date', 'gender', 'marital_status', 'religion_id', 'nationality_id', 'expected_salary', 'expected_salary_period_id',
                'experience_level_id', 'availability', 'available_from', 'province_id', 'city_id', 'subdistrict_id', 'village_id', 'postal_code_id', 'address_line',
                'headline', 'summary', 'is_discoverable', 'resume_discoverable', 'updated_at',
            ],
        );
    }

    public function lock(int $userId, DateTimeImmutable $at): void
    {
        $time = $at->format(self::TIME_FORMAT);

        $this->connection->table('jobseeker_profiles')->insertOrIgnore(['user_id' => $userId, 'created_at' => $time, 'updated_at' => $time]);
        $this->connection->table('jobseeker_profiles')->where('user_id', $userId)->lockForUpdate()->first(['id']);
    }

    public function counts(int $userId): array
    {
        return [
            'experiences' => $this->connection->table('jobseeker_experiences')->where('user_id', $userId)->count(),
            'educations' => $this->connection->table('jobseeker_educations')->where('user_id', $userId)->count(),
            'resumes' => $this->connection->table('jobseeker_resumes')->where('user_id', $userId)->count(),
        ];
    }

    private static function hydrate(stdClass $row): JobSeekerProfile
    {
        $utc = new DateTimeZone('UTC');
        $date = static fn (?string $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable($value, $utc);
        $id = static fn (mixed $value): ?int => $value === null ? null : (int) $value;

        return new JobSeekerProfile(
            (int) $row->user_id,
            $row->phone,
            $row->whatsapp,
            $date($row->birth_date),
            $row->gender === null ? null : Gender::from($row->gender),
            $row->marital_status === null ? null : MaritalStatus::from($row->marital_status),
            $id($row->religion_id),
            $id($row->nationality_id),
            $id($row->expected_salary),
            $id($row->expected_salary_period_id),
            $id($row->experience_level_id),
            $row->availability === null ? null : Availability::from($row->availability),
            $date($row->available_from),
            $id($row->province_id),
            $id($row->city_id),
            $id($row->subdistrict_id),
            $id($row->village_id),
            $id($row->postal_code_id),
            $row->address_line,
            $row->headline,
            $row->summary,
            (bool) $row->is_discoverable,
            (bool) $row->resume_discoverable,
        );
    }
}
