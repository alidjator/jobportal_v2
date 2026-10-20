<?php

namespace Src\Identity\Application\EmailChange;

use Src\Identity\Domain\EmailChangeRepository;
use Src\Shared\Application\Actor;

/** Drops the pending email change of the signed-in account; nothing happens when there is none. */
final readonly class CancelEmailChange
{
    public function __construct(private EmailChangeRepository $changes) {}

    public function execute(Actor $actor): void
    {
        $this->changes->delete((int) $actor->id);
    }
}
