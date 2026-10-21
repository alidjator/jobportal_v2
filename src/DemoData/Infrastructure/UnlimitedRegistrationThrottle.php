<?php

namespace Src\DemoData\Infrastructure;

use Src\Identity\Application\Auth\RegistrationThrottle;

/** A demo run registers all its job seekers from one console process, which the per-IP registration limit would stop; the run is not limited. */
final readonly class UnlimitedRegistrationThrottle implements RegistrationThrottle
{
    public function hit(?string $ipAddress, string $email): void {}
}
