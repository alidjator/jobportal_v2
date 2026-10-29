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

    /** The ERP characters (`docs/design/erp-installation-type.md`): one company, or several. */
    public const string TYPE_SINGLE = 'single';

    public const string TYPE_MULTI = 'multi';

    /** The kinds of `multi`: the legal entities of one group, or companies with no relation to each other (the marketplace). */
    public const string KIND_GROUP = 'group';

    public const string KIND_INDEPENDENT = 'independent';

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

    /** `single` for a company portal of one company; `multi` for every other portal. */
    public static function installationTypeOf(string $mode, ?string $scope): string
    {
        return $mode === self::COMPANY && $scope === self::SCOPE_COMPANY ? self::TYPE_SINGLE : self::TYPE_MULTI;
    }

    /** The kind of a `multi` portal (`group` or `independent`); null for `single`. */
    public static function multiKindOf(string $mode, ?string $scope): ?string
    {
        if (self::installationTypeOf($mode, $scope) === self::TYPE_SINGLE) {
            return null;
        }

        return $mode === self::MARKETPLACE ? self::KIND_INDEPENDENT : self::KIND_GROUP;
    }

    public function installationType(): string
    {
        return self::installationTypeOf($this->mode, $this->scope);
    }

    public function multiKind(): ?string
    {
        return self::multiKindOf($this->mode, $this->scope);
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
