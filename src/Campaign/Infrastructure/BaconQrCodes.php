<?php

namespace Src\Campaign\Infrastructure;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Src\Campaign\Application\QrCodes;

/** Draws the QR code as an SVG with `bacon/bacon-qr-code` (pure PHP, no image extension needed). */
final readonly class BaconQrCodes implements QrCodes
{
    private const int SIZE = 400;

    private const int MARGIN = 2;

    public function svg(string $text): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(self::SIZE, self::MARGIN), new SvgImageBackEnd)))->writeString($text);
    }
}
