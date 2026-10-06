<?php

namespace Src\Identity\Application\Account;

/** The picture of an account, ready to be sent: its bytes and media type. */
final readonly class AvatarImage
{
    public function __construct(public string $contents, public string $mimeType) {}
}
