<?php

namespace Src\Messaging\Application;

/** A file that arrived with a message: its bytes and the name the client gave it (for display only). */
final readonly class AttachmentUpload
{
    public function __construct(public string $name, public string $contents) {}
}
