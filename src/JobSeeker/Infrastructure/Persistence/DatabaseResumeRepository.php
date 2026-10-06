<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\Resume;
use Src\JobSeeker\Domain\ResumeRepository;
use stdClass;

final readonly class DatabaseResumeRepository implements ResumeRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forUser(int $userId): array
    {
        return $this->connection->table('jobseeker_resumes')->where('user_id', $userId)->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(static fn (stdClass $row): Resume => self::hydrate($row))
            ->all();
    }

    public function find(int $userId, int $id): ?Resume
    {
        $row = $this->connection->table('jobseeker_resumes')->where('user_id', $userId)->where('id', $id)->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function count(int $userId): int
    {
        return $this->connection->table('jobseeker_resumes')->where('user_id', $userId)->count();
    }

    public function add(Resume $resume): int
    {
        return (int) $this->connection->table('jobseeker_resumes')->insertGetId([
            'user_id' => $resume->userId,
            'name' => $resume->name,
            'path' => $resume->path,
            'size_bytes' => $resume->sizeBytes,
            'created_at' => $resume->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function rename(int $userId, int $id, string $name): void
    {
        $this->connection->table('jobseeker_resumes')->where('user_id', $userId)->where('id', $id)->update(['name' => $name]);
    }

    public function delete(int $userId, int $id): void
    {
        $this->connection->table('jobseeker_resumes')->where('user_id', $userId)->where('id', $id)->delete();
    }

    private static function hydrate(stdClass $row): Resume
    {
        return new Resume((int) $row->user_id, $row->name, $row->path, (int) $row->size_bytes, new DateTimeImmutable($row->created_at, new DateTimeZone('UTC')), (int) $row->id);
    }
}
