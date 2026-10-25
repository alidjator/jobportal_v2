<?php

namespace Src\Shared\Application\Portal;

/**
 * The brand of a career site: a name that replaces the name of the company or group, a slogan and a welcome text in each language, and the appearance settings of the template's
 * style switcher that fit a career site: the colour preset (1 to 18), the theme mode (`light`, `dark` or `system`: the visitor's own setting decides), the contrast, the page
 * width (`container` or `full`) and a header in the brand colour. A blank text is null; the site then shows the default.
 */
final readonly class PortalBrandValues
{
    public const int NAME_MAX = 120;

    public const int TAGLINE_MAX = 160;

    public const int WELCOME_MAX = 500;

    public const int PRESETS = 18;

    /** @var list<string> */
    public const array THEME_MODES = ['light', 'dark', 'system'];

    /** @var list<string> */
    public const array WIDTHS = ['container', 'full'];

    public function __construct(
        public ?string $name,
        public ?string $taglineId,
        public ?string $taglineEn,
        public ?string $welcomeId,
        public ?string $welcomeEn,
        public int $preset,
        public string $themeMode = 'light',
        public bool $contrast = false,
        public string $width = 'container',
        public bool $headerColor = false,
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
            self::choice($data['theme_mode'] ?? null, self::THEME_MODES),
            filter_var($data['contrast'] ?? false, FILTER_VALIDATE_BOOLEAN),
            self::choice($data['width'] ?? null, self::WIDTHS),
            filter_var($data['header_color'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /** @return array{name: string|null, tagline_id: string|null, tagline_en: string|null, welcome_id: string|null, welcome_en: string|null, preset: int, theme_mode: string, contrast: bool, width: string, header_color: bool} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'tagline_id' => $this->taglineId,
            'tagline_en' => $this->taglineEn,
            'welcome_id' => $this->welcomeId,
            'welcome_en' => $this->welcomeEn,
            'preset' => $this->preset,
            'theme_mode' => $this->themeMode,
            'contrast' => $this->contrast,
            'width' => $this->width,
            'header_color' => $this->headerColor,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /** @param  list<string>  $allowed the first one is the default */
    private static function choice(mixed $value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    private static function text(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }
}
