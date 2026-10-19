<?php

namespace Src\Company\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Src\Shared\Domain\Address;
use Src\Shared\Domain\Coordinates;

/**
 * A work location of a tenant: head office, branch or site. Every tenant has exactly one main location, which is always active. The time zone is not stored: it follows
 * from the province (`LocationTimeZones`).
 */
final readonly class CompanyLocation
{
    public const int NAME_MAX = 100;

    public const string DEFAULT_MAIN_NAME = 'Kantor Pusat';

    public function __construct(
        public ?int $id,
        public int $companyId,
        public string $name,
        public LocationType $type,
        public bool $isMain,
        public LocationStatus $status,
        public Address $address,
        public ?string $phone,
        public ?Coordinates $coordinates,
        public ?DateTimeImmutable $deactivatedAt = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /** The main location a new company starts with: "Kantor Pusat", a head office, without an address. */
    public static function main(int $companyId, Address $address = new Address): self
    {
        return new self(null, $companyId, self::DEFAULT_MAIN_NAME, LocationType::HeadOffice, true, LocationStatus::Active, $address, null, null);
    }

    public function isActive(): bool
    {
        return $this->status === LocationStatus::Active;
    }

    public function deactivate(DateTimeImmutable $now): self
    {
        return $this->with(['status' => LocationStatus::Inactive, 'deactivatedAt' => $now]);
    }

    public function activate(): self
    {
        return $this->with(['status' => LocationStatus::Active, 'deactivatedAt' => null]);
    }

    /**
     * A copy with some fields replaced. The keys are property names; a key that is not a property is a programming error and throws.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        $values = get_object_vars($this);

        foreach (array_keys($changes) as $key) {
            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException("A company location has no field {$key}.");
            }
        }

        return new self(...[...$values, ...$changes]);
    }
}
