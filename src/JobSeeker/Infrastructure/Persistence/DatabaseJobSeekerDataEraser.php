<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\JobSeekerDataEraser;

final readonly class DatabaseJobSeekerDataEraser implements JobSeekerDataEraser
{
    private const array TABLES = [
        'jobseeker_profiles', 'jobseeker_experiences', 'jobseeker_educations', 'jobseeker_skills', 'jobseeker_languages', 'jobseeker_links', 'jobseeker_resumes',
    ];

    public function __construct(private ConnectionInterface $connection) {}

    public function deleteRows(int $userId): void
    {
        foreach (self::TABLES as $table) {
            $this->connection->table($table)->where('user_id', $userId)->delete();
        }
    }
}
