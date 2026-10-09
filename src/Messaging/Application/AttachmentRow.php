<?php

namespace Src\Messaging\Application;

final readonly class AttachmentRow
{
    public function __construct(public int $id, public string $name, public string $mime, public int $sizeBytes) {}
}
