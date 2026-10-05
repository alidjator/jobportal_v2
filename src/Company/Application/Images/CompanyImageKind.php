<?php

namespace Src\Company\Application\Images;

/** The two pictures a company can have, with where they are stored, how large they may be and which setting limits the uploaded file. */
enum CompanyImageKind: string
{
    case Logo = 'logo';
    case Banner = 'banner';

    public function directory(): string
    {
        return match ($this) {
            self::Logo => 'company/logos',
            self::Banner => 'company/banners',
        };
    }

    /** The largest width of the stored image in pixels; a larger image is shrunk to fit. */
    public function maxWidth(): int
    {
        return match ($this) {
            self::Logo => 512,
            self::Banner => 1600,
        };
    }

    public function maxHeight(): int
    {
        return match ($this) {
            self::Logo => 512,
            self::Banner => 600,
        };
    }

    /** The key of the setting that holds the largest accepted upload, in kilobytes. */
    public function maxSizeSetting(): string
    {
        return match ($this) {
            self::Logo => CompanySettings::LOGO_MAX_SIZE_KB,
            self::Banner => CompanySettings::BANNER_MAX_SIZE_KB,
        };
    }
}
