<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Src\LegacyImport\Application\JobSeekerWriter;

final readonly class DatabaseJobSeekerWriter implements JobSeekerWriter
{
    private const array TABLES = [
        'profile' => 'jobseeker_profiles',
        'experience' => 'jobseeker_experiences',
        'education' => 'jobseeker_educations',
        'skill' => 'jobseeker_skills',
        'language' => 'jobseeker_languages',
        'link' => 'jobseeker_links',
        'resume' => 'jobseeker_resumes',
    ];

    public function __construct(private ConnectionInterface $connection) {}

    public function insert(string $kind, array $columns): int
    {
        return (int) $this->connection->table($this->table($kind))->insertGetId($columns);
    }

    public function count(string $kind, int $userId): int
    {
        return $this->connection->table($this->table($kind))->where('user_id', $userId)->count();
    }

    private function table(string $kind): string
    {
        return self::TABLES[$kind] ?? throw new InvalidArgumentException("A job seeker has no \"{$kind}\" rows.");
    }
}
