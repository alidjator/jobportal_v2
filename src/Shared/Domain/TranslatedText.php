<?php

namespace Src\Shared\Domain;

/**
 * A text in the supported languages (`id` and `en`), stored as one JSON column. Only the supported languages are kept and
 * values are trimmed. A public response shows one language (`resolve`); an admin response shows all of them (`toArray`).
 */
final readonly class TranslatedText
{
    /** @var list<string> */
    public const array LANGUAGES = ['id', 'en'];

    /** @param  array<string, string>  $values */
    private function __construct(private array $values) {}

    /** @param  array<array-key, mixed>  $values */
    public static function fromArray(array $values): self
    {
        $clean = [];

        foreach (self::LANGUAGES as $language) {
            $value = $values[$language] ?? null;
            $clean[$language] = is_string($value) ? trim($value) : '';
        }

        return new self($clean);
    }

    /** The text in the preferred language; when that is empty, the first supported language that has one; otherwise an empty string. */
    public function resolve(string $language): string
    {
        $preferred = $this->values[$language] ?? '';

        if ($preferred !== '') {
            return $preferred;
        }

        foreach ($this->values as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function isCompleteInAllLanguages(): bool
    {
        return ! in_array('', $this->values, true);
    }

    /** @return array<string, string> every supported language as a key; a missing translation is an empty string */
    public function toArray(): array
    {
        return $this->values;
    }
}
