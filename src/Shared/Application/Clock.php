<?php

namespace Src\Shared\Application;

use DateTimeImmutable;

/** The current time, injected so use cases and tests do not depend on the system clock. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
