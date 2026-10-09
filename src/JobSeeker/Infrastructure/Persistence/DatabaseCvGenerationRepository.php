<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\CvGenerationRepository;

final readonly class DatabaseCvGenerationRepository implements CvGenerationRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function countSince(int $userId, DateTimeImmutable $since): int
    {
        return $this->connection->table('jobseeker_cv_generations')->where('user_id', $userId)->where('created_at', '>', $this->format($since))->count();
    }

    public function record(int $userId, DateTimeImmutable $at, DateTimeImmutable $keepFrom): void
    {
        $this->connection->table('jobseeker_cv_generations')->where('user_id', $userId)->where('created_at', '<', $this->format($keepFrom))->delete();
        $this->connection->table('jobseeker_cv_generations')->insert(['user_id' => $userId, 'created_at' => $this->format($at)]);
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
