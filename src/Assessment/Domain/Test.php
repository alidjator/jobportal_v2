<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A test: a named set of sections with instructions. A draft is built and edited, a published test can be used, an archived one is kept for
 * what already refers to it. The owner never changes.
 */
final class Test
{
    public const int CAPABILITIES_MAX = 10;

    public const int CAPABILITY_MAX_LENGTH = 60;

    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public readonly ?int $id,
        public readonly TestOwner $owner,
        private string $title,
        private ?string $description,
        private ?string $instructions,
        private array $capabilities,
        private TestStatus $status,
        public readonly ?int $createdByUserId,
        public readonly ?int $createdByAdminId,
        private ?DateTimeImmutable $publishedAt = null,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * @param  list<string>  $capabilities
     */
    public static function draft(TestOwner $owner, string $title, ?string $description, ?string $instructions, array $capabilities, ?int $createdByUserId, ?int $createdByAdminId): self
    {
        return new self(null, $owner, $title, $description, $instructions, $capabilities, TestStatus::Draft, $createdByUserId, $createdByAdminId);
    }

    public function kind(): TestKind
    {
        return $this->owner->kind();
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function instructions(): ?string
    {
        return $this->instructions;
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function status(): TestStatus
    {
        return $this->status;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isDraft(): bool
    {
        return $this->status === TestStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === TestStatus::Published;
    }

    public function isArchived(): bool
    {
        return $this->status === TestStatus::Archived;
    }

    /**
     * @param  list<string>  $capabilities
     *
     * @throws StateConflict `test-archived`
     */
    public function describe(string $title, ?string $description, ?string $instructions, array $capabilities): void
    {
        $this->assertEditable();

        $this->title = $title;
        $this->description = $description;
        $this->instructions = $instructions;
        $this->capabilities = $capabilities;
    }

    /**
     * @throws StateConflict `test-archived`
     */
    public function assertEditable(): void
    {
        if ($this->isArchived()) {
            throw new StateConflict('An archived test cannot be changed. Restore it first.', 'test-archived');
        }
    }

    /**
     * @throws StateConflict `test-not-draft`
     */
    public function publish(DateTimeImmutable $now): void
    {
        if (! $this->isDraft()) {
            throw new StateConflict('Only a draft can be published.', 'test-not-draft');
        }

        $this->status = TestStatus::Published;
        $this->publishedAt = $now;
    }

    public function archive(): void
    {
        $this->status = TestStatus::Archived;
    }

    /** An archived test that was published comes back published; one that never was comes back as a draft. */
    public function restore(): void
    {
        if ($this->isArchived()) {
            $this->status = $this->publishedAt === null ? TestStatus::Draft : TestStatus::Published;
        }
    }
}
