<?php

namespace Src\Organization\Domain;

use Src\Shared\Domain\Exceptions\StateConflict;

/** A legal entity of a group: the legal data only (no legal documents yet). An internal tenant of the portal is linked to exactly one. */
final readonly class LegalEntity
{
    public function __construct(
        public ?int $id,
        public int $groupId,
        public string $legalName,
        public LegalForm $legalForm,
        public ?string $npwp,
        public ?string $nib,
        public ?string $addressLine,
        public ?int $provinceId,
        public ?int $cityId,
        public ?int $subdistrictId,
        public ?int $villageId,
        public ?int $postalCodeId,
        public LegalEntityStatus $status = LegalEntityStatus::Active,
    ) {}

    public function isActive(): bool
    {
        return $this->status === LegalEntityStatus::Active;
    }

    /**
     * The entity with these fields changed, keyed by property name; the status and the group never change here.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        $values = [...get_object_vars($this), ...$changes];

        return new self(
            $this->id, $this->groupId, $values['legalName'], $values['legalForm'], $values['npwp'], $values['nib'], $values['addressLine'], $values['provinceId'], $values['cityId'],
            $values['subdistrictId'], $values['villageId'], $values['postalCodeId'], $this->status,
        );
    }

    /** @throws StateConflict `legal-entity-already-inactive` */
    public function deactivate(): self
    {
        if (! $this->isActive()) {
            throw new StateConflict('The legal entity is already inactive.', 'legal-entity-already-inactive');
        }

        return $this->withStatus(LegalEntityStatus::Inactive);
    }

    /** @throws StateConflict `legal-entity-already-active` */
    public function activate(): self
    {
        if ($this->isActive()) {
            throw new StateConflict('The legal entity is already active.', 'legal-entity-already-active');
        }

        return $this->withStatus(LegalEntityStatus::Active);
    }

    private function withStatus(LegalEntityStatus $status): self
    {
        return new self(
            $this->id, $this->groupId, $this->legalName, $this->legalForm, $this->npwp, $this->nib, $this->addressLine, $this->provinceId, $this->cityId,
            $this->subdistrictId, $this->villageId, $this->postalCodeId, $status,
        );
    }
}
