<?php

namespace Src\Messaging\Application;

/** A stored attachment ready to be sent. */
final readonly class AttachmentFile
{
    public function __construct(public string $contents, public string $name, public string $mime) {}
}
