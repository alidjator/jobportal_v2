<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\ApplicationRatingRepository;

final readonly class DatabaseApplicationRatingRepository implements ApplicationRatingRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function set(int $applicationId, int $memberId, int $stars, DateTimeImmutable $at): void
    {
        $time = $at->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);

        $this->connection->table('recruitment_application_ratings')->upsert(
            [['application_id' => $applicationId, 'member_id' => $memberId, 'stars' => $stars, 'created_at' => $time, 'updated_at' => $time]],
            ['application_id', 'member_id'],
            ['stars', 'updated_at'],
        );
    }

    public function remove(int $applicationId, int $memberId): void
    {
        $this->connection->table('recruitment_application_ratings')->where('application_id', $applicationId)->where('member_id', $memberId)->delete();
    }

    public function forApplication(int $applicationId): array
    {
        $stars = [];

        foreach ($this->connection->table('recruitment_application_ratings')->where('application_id', $applicationId)->orderBy('id')->get(['member_id', 'stars']) as $row) {
            $stars[(int) $row->member_id] = (int) $row->stars;
        }

        return $stars;
    }
}
