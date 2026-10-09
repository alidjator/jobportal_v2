<?php

namespace Src\Campaign\Application;

interface QrCodes
{
    /** A QR code of the text as an SVG image. */
    public function svg(string $text): string;
}
