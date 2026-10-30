<?php

namespace Src\Shared\Application\Job;

/**
 * One extra item of the profile a job asks for before an application is taken (`docs/design/phase-8-job-posting.md` section 5): an expected salary, a portfolio link, a LinkedIn link, a
 * WhatsApp number, or a language at a minimum proficiency. The list of items is closed, so nothing about a protected attribute can be required.
 */
final readonly class ApplyRequirement
{
    public const string EXPECTED_SALARY = 'expected_salary';

    public const string PORTFOLIO = 'portfolio';

    public const string LINKEDIN = 'linkedin';

    public const string WHATSAPP = 'whatsapp';

    public const string LANGUAGE = 'language';

    public const array ITEMS = [self::EXPECTED_SALARY, self::PORTFOLIO, self::LINKEDIN, self::WHATSAPP, self::LANGUAGE];

    /** From the lowest to the highest, as the profile stores them. */
    public const array PROFICIENCIES = ['basic', 'conversational', 'fluent', 'native'];

    public const int MAX_LANGUAGES = 3;

    public function __construct(public string $item, public ?int $languageId = null, public ?string $minProficiency = null) {}

    /** The code a missing item has in `profile-incomplete`: the item, or `language:{id}`. */
    public function code(): string
    {
        return $this->item === self::LANGUAGE ? self::LANGUAGE.':'.$this->languageId : $this->item;
    }

    /** @return array{item: string, language_id?: int|null, min_proficiency?: string|null} */
    public function toArray(): array
    {
        return $this->item === self::LANGUAGE
            ? ['item' => $this->item, 'language_id' => $this->languageId, 'min_proficiency' => $this->minProficiency]
            : ['item' => $this->item];
    }

    /** @param  array<string, mixed>  $values */
    public static function fromArray(array $values): self
    {
        return new self(
            (string) ($values['item'] ?? ''),
            isset($values['language_id']) ? (int) $values['language_id'] : null,
            isset($values['min_proficiency']) ? (string) $values['min_proficiency'] : null,
        );
    }
}
