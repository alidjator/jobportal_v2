<?php

namespace Src\Assessment\Application\Delivery;

/** The file of an invitation, ready to be sent as a download: its bytes, media type and the cleaned name the client gave it. */
final readonly class AttachmentFile
{
    public function __construct(public string $contents, public string $mimeType, public string $name) {}
}
