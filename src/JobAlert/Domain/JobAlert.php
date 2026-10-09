<?php

namespace Src\JobAlert\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A saved job search of a job seeker that tells them about new jobs. `lastRunAt` is the cursor: only jobs that first went live after it are ever sent, so a new,
 * resumed or re-timed alert starts from now and sends no backlog (a summary moves it to the moment it covered). Immutable: a change returns a new instance.
 */
final readonly class JobAlert
{
    public const int MAX_NAME_LENGTH = 100;

    /** The most emails one immediate alert sends per Jakarta day (counted by the repository); further jobs only get the in-app notification. */
    public const int MAX_EMAILS_PER_DAY = 5;

    public function __construct(
        public int $userId,
        public string $name,
        public AlertFrequency $frequency,
        public AlertCriteria $criteria,
        public bool $email,
        public bool $inApp,
        public bool $paused,
        public DateTimeImmutable $lastRunAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    /** @throws BusinessRuleViolation `invalid-name` or `channel-required` */
    public static function create(int $userId, string $name, AlertFrequency $frequency, AlertCriteria $criteria, bool $email, bool $inApp, DateTimeImmutable $now): self
    {
        return new self($userId, self::cleanName($name), $frequency, self::assertChannel($criteria, $email, $inApp), $email, $inApp, false, $now, $now, $now);
    }

    /** @throws BusinessRuleViolation `invalid-name` or `channel-required` */
    public function revise(string $name, AlertFrequency $frequency, AlertCriteria $criteria, bool $email, bool $inApp, DateTimeImmutable $now): self
    {
        self::assertChannel($criteria, $email, $inApp);

        return new self(
            $this->userId, self::cleanName($name), $frequency, $criteria, $email, $inApp, $this->paused,
            // A new rhythm starts from now: nothing is sent twice and nothing from before is sent late.
            $frequency === $this->frequency ? $this->lastRunAt : $now,
            $this->createdAt, $now, $this->id,
        );
    }

    public function pause(DateTimeImmutable $now): self
    {
        return $this->paused ? $this : $this->with(paused: true, lastRunAt: $this->lastRunAt, updatedAt: $now);
    }

    /** Resuming moves the cursor to now: what went live while it was paused is not sent. */
    public function resume(DateTimeImmutable $now): self
    {
        return $this->paused ? $this->with(paused: false, lastRunAt: $now, updatedAt: $now) : $this;
    }

    /** A summary covered everything up to `$at`. */
    public function ranUntil(DateTimeImmutable $at): self
    {
        return $this->with(paused: $this->paused, lastRunAt: $at, updatedAt: $this->updatedAt);
    }

    private function with(bool $paused, DateTimeImmutable $lastRunAt, DateTimeImmutable $updatedAt): self
    {
        return new self(
            $this->userId, $this->name, $this->frequency, $this->criteria, $this->email, $this->inApp, $paused, $lastRunAt,
            $this->createdAt, $updatedAt, $this->id,
        );
    }

    private static function assertChannel(AlertCriteria $criteria, bool $email, bool $inApp): AlertCriteria
    {
        if (! $email && ! $inApp) {
            throw new BusinessRuleViolation('Turn on at least one way to be told: email or in-app.', 'channel-required');
        }

        return $criteria;
    }

    private static function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new BusinessRuleViolation('The name must be 1 to '.self::MAX_NAME_LENGTH.' characters.', 'invalid-name');
        }

        return $name;
    }
}
