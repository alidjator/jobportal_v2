<?php

namespace Src\Assessment\Domain;

/** The one file that goes with an invitation (a PDF, JPEG or PNG on the private disk). `name` is the client's name, cleaned, for display and download only. */
final readonly class InvitationAttachment
{
    public function __construct(
        public string $path,
        public string $name,
        public string $mimeType,
        public int $sizeBytes,
    ) {}
}
