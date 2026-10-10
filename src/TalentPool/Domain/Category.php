<?php

namespace Src\TalentPool\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A company's label for saved candidates. The name is 1 to 50 characters after trimming. */
final readonly class Category
{
    public const int MAX_NAME_LENGTH = 50;

    public function __construct(
        public int $companyId,
        public string $name,
        public DateTimeImmutable $createdAt,
        public ?int $id = null,
    ) {}

    /** @throws BusinessRuleViolation `category-name-invalid` */
    public static function named(int $companyId, string $name, DateTimeImmutable $now): self
    {
        return new self($companyId, self::cleanName($name), $now);
    }

    /** @throws BusinessRuleViolation `category-name-invalid` */
    public static function cleanName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new BusinessRuleViolation('A category name has 1 to '.self::MAX_NAME_LENGTH.' characters.', 'category-name-invalid');
        }

        return $name;
    }
}
