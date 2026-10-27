<?php

namespace Src\Shared\Application\Accounts;

/** The profile picture of an account, ready to be sent: its bytes and media type. */
final readonly class AccountAvatar
{
    public function __construct(public string $contents, public string $mimeType) {}
}
