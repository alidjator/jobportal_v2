<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** An internal note on an application, written by a member of the company's team. The job seeker never sees it. */
final readonly class ApplicationNote
{
    public const int BODY_MAX = 2000;

    public function __construct(
        public int $applicationId,
        public int $authorId,
        public string $body,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $editedAt = null,
        public ?int $id = null,
    ) {}

    /** @throws BusinessRuleViolation `invalid-note` */
    public static function write(int $applicationId, int $authorId, string $body, DateTimeImmutable $now): self
    {
        return new self($applicationId, $authorId, self::checked($body), $now);
    }

    /**
     * The note after its author changed the text; the same text changes nothing.
     *
     * @throws BusinessRuleViolation `invalid-note`
     */
    public function edit(string $body, DateTimeImmutable $now): self
    {
        $body = self::checked($body);

        if ($body === $this->body) {
            return $this;
        }

        return new self($this->applicationId, $this->authorId, $body, $this->createdAt, $now, $this->id);
    }

    /** @throws BusinessRuleViolation `invalid-note` */
    private static function checked(string $body): string
    {
        $body = trim($body);

        $message = match (true) {
            $body === '' => 'Write the note.',
            mb_strlen($body) > self::BODY_MAX => 'A note can have at most '.self::BODY_MAX.' characters.',
            default => null,
        };

        if ($message !== null) {
            throw new BusinessRuleViolation($message, 'invalid-note', extensions: ['errors' => ['body' => [$message]]]);
        }

        return $body;
    }
}
