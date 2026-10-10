<?php

namespace Src\Newsletter\Domain;

use DateTimeImmutable;

/** One issue of the newsletter. A draft can be edited and deleted; once it is sending or sent it is fixed. */
final readonly class Issue
{
    public const int SUBJECT_MAX = 150;

    public const int BODY_MAX = 20000;

    public function __construct(
        public string $subject,
        public string $body,
        public IssueStatus $status,
        public int $createdByAdminId,
        public ?int $sentByAdminId,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt,
        public int $recipients,
        public int $delivered,
        public int $failed,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    public static function draft(string $subject, string $body, int $adminId, DateTimeImmutable $now): self
    {
        return new self($subject, $body, IssueStatus::Draft, $adminId, null, null, null, 0, 0, 0, $now, $now);
    }

    public function isDraft(): bool
    {
        return $this->status === IssueStatus::Draft;
    }

    public function edited(string $subject, string $body, DateTimeImmutable $now): self
    {
        return new self($subject, $body, $this->status, $this->createdByAdminId, $this->sentByAdminId, $this->startedAt, $this->finishedAt, $this->recipients, $this->delivered, $this->failed, $this->createdAt, $now, $this->id);
    }

    public function sending(int $adminId, int $recipients, DateTimeImmutable $now): self
    {
        return new self($this->subject, $this->body, IssueStatus::Sending, $this->createdByAdminId, $adminId, $now, null, $recipients, 0, 0, $this->createdAt, $now, $this->id);
    }
}
