<?php

namespace Src\Shared\Application\Portal;

/**
 * The brand of a career site: a name that replaces the name of the company or group, a slogan and a welcome text in each language, and the colour preset (1 to 10).
 * A blank text is null; the site then shows the default.
 */
final readonly class PortalBrandValues
{
    public const int NAME_MAX = 120;

    public const int TAGLINE_MAX = 160;

    public const int WELCOME_MAX = 500;

    public const int PRESETS = 10;

    public function __construct(
        public ?string $name,
        public ?string $taglineId,
        public ?string $taglineEn,
        public ?string $welcomeId,
        public ?string $welcomeEn,
        public int $preset,
    ) {}

    public static function default(): self
    {
        return new self(null, null, null, null, null, 1);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            self::text($data['name'] ?? null),
            self::text($data['tagline_id'] ?? null),
            self::text($data['tagline_en'] ?? null),
            self::text($data['welcome_id'] ?? null),
            self::text($data['welcome_en'] ?? null),
            min(self::PRESETS, max(1, (int) ($data['preset'] ?? 1))),
        );
    }

    /** @return array{name: string|null, tagline_id: string|null, tagline_en: string|null, welcome_id: string|null, welcome_en: string|null, preset: int} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'tagline_id' => $this->taglineId,
            'tagline_en' => $this->taglineEn,
            'welcome_id' => $this->welcomeId,
            'welcome_en' => $this->welcomeEn,
            'preset' => $this->preset,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    private static function text(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }
}
