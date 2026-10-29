<?php

namespace Src\Assessment\Domain;

/** What an institution tells the world about itself on its reports and to the candidate: address, practice licence, contact and logo (`docs/design/phase-8-provider-institutions.md` section 3). Every part is optional. */
final readonly class ProviderIdentity
{
    private const array TEXT_FIELDS = ['address', 'practice_licence_number', 'phone', 'email'];

    public function __construct(
        public ?string $address = null,
        public ?string $practiceLicenceNumber = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $logoPath = null,
    ) {}

    /**
     * The identity with the given text fields changed and the others left alone; a blank value clears a field. Keys: `address`, `practice_licence_number`, `phone`, `email`.
     *
     * @param  array<string, string|null>  $changes
     */
    public function with(array $changes): self
    {
        $values = [
            'address' => $this->address,
            'practice_licence_number' => $this->practiceLicenceNumber,
            'phone' => $this->phone,
            'email' => $this->email,
        ];

        foreach (self::TEXT_FIELDS as $field) {
            if (array_key_exists($field, $changes)) {
                $value = $changes[$field] === null ? '' : trim($changes[$field]);
                $values[$field] = $value === '' ? null : $value;
            }
        }

        return new self($values['address'], $values['practice_licence_number'], $values['phone'], $values['email'], $this->logoPath);
    }

    public function withLogo(?string $path): self
    {
        return new self($this->address, $this->practiceLicenceNumber, $this->phone, $this->email, $path);
    }
}
