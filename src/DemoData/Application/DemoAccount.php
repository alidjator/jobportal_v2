<?php

namespace Src\DemoData\Application;

/** One account the demo run created, as the operator's summary lists it: what it is for, and how to sign in (the password is the run's one password). */
final readonly class DemoAccount
{
    public function __construct(
        public string $application,
        public string $role,
        public string $name,
        public string $email,
    ) {}
}
