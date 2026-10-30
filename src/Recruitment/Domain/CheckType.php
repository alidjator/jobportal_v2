<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A kind of check a company defines (MCU, background check, reference check, SKCK, diploma verification). Immutable: a change returns a new instance. A type is never deleted, only deactivated. */
final readonly class CheckType
{
    public const int NAME_MAX = 100;

    public const int INSTRUCTIONS_MAX = 2000;

    public function __construct(
        public int $companyId,
        public string $name,
        public CheckKind $kind,
        public ?string $instructions,
        public bool $requiresConsent,
        public bool $isActive,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    /** @throws BusinessRuleViolation `invalid-check-type` */
    public static function create(int $companyId, string $name, CheckKind $kind, ?string $instructions, bool $requiresConsent, DateTimeImmutable $now): self
    {
        return new self($companyId, self::checkedName($name), $kind, self::checkedInstructions($instructions), $kind->forcesConsent() || $requiresConsent, true, $now, $now);
    }

    /**
     * The kind stays: a check already requested under the type keeps its meaning.
     *
     * @throws BusinessRuleViolation `invalid-check-type`
     */
    public function changed(string $name, ?string $instructions, bool $requiresConsent, DateTimeImmutable $now): self
    {
        return new self(
            $this->companyId, self::checkedName($name), $this->kind, self::checkedInstructions($instructions), $this->kind->forcesConsent() || $requiresConsent, $this->isActive,
            $this->createdAt, $now, $this->id,
        );
    }

    public function withActive(bool $active, DateTimeImmutable $now): self
    {
        return new self($this->companyId, $this->name, $this->kind, $this->instructions, $this->requiresConsent, $active, $this->createdAt, $now, $this->id);
    }

    /** @throws BusinessRuleViolation `invalid-check-type` */
    private static function checkedName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            throw new BusinessRuleViolation('The name of a check type has 1 to :max characters.', 'invalid-check-type', extensions: ['errors' => ['name' => ['The name of a check type has 1 to :max characters.']]], parameters: ['max' => self::NAME_MAX]);
        }

        return $name;
    }

    /** @throws BusinessRuleViolation `invalid-check-type` */
    private static function checkedInstructions(?string $instructions): ?string
    {
        $instructions = $instructions === null ? null : trim($instructions);

        if ($instructions === null || $instructions === '') {
            return null;
        }

        if (mb_strlen($instructions) > self::INSTRUCTIONS_MAX) {
            throw new BusinessRuleViolation('The instructions have at most :max characters.', 'invalid-check-type', extensions: ['errors' => ['instructions' => ['The instructions have at most :max characters.']]], parameters: ['max' => self::INSTRUCTIONS_MAX]);
        }

        return $instructions;
    }
}
