<?php

namespace Src\Platform\Application\Portal;

/**
 * Who the career site is for: the marketplace (every company, no scope), or a company site (`mode` company) of one company (`scope` company) or of the companies of one group
 * (`scope` group). `companyIds` are the companies as configured; which of them can be shown today is a separate question (`GetPortal`).
 */
final readonly class PortalIdentity
{
    public const string MARKETPLACE = 'marketplace';

    public const string COMPANY = 'company';

    public const string SCOPE_GROUP = 'group';

    public const string SCOPE_COMPANY = 'company';

    /** @param  list<int>  $companyIds */
    public function __construct(public string $mode, public ?string $scope, public array $companyIds) {}

    public static function marketplace(): self
    {
        return new self(self::MARKETPLACE, null, []);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $ids = array_values(array_unique(array_map('intval', is_array($data['company_ids'] ?? null) ? $data['company_ids'] : [])));

        return new self(
            (string) ($data['mode'] ?? self::MARKETPLACE),
            isset($data['scope']) && is_string($data['scope']) ? $data['scope'] : null,
            $ids,
        );
    }

    /** @return array{mode: string, scope: string|null, company_ids: list<int>} */
    public function toArray(): array
    {
        return ['mode' => $this->mode, 'scope' => $this->scope, 'company_ids' => $this->companyIds];
    }

    public function isMarketplace(): bool
    {
        return $this->mode === self::MARKETPLACE;
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
