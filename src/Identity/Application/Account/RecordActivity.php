<?php

namespace Src\Identity\Application\Account;

use DateTimeImmutable;
use DateTimeZone;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Clock;

/** Notes that a job seeker used the API today (a Jakarta day); see `UserRepository::recordActiveOn`. Companies see this only as a bucket (`active_within`). */
final readonly class RecordActivity
{
    private const string TIME_ZONE = 'Asia/Jakarta';

    public function __construct(private UserRepository $users, private Clock $clock) {}

    public function execute(int $userId): void
    {
        $today = $this->clock->now()->setTimezone(new DateTimeZone(self::TIME_ZONE))->format('Y-m-d');

        $this->users->recordActiveOn($userId, new DateTimeImmutable($today, new DateTimeZone('UTC')));
    }
}
