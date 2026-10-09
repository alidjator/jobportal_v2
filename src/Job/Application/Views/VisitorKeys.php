<?php

namespace Src\Job\Application\Views;

/** The key that stands for a viewer who is not signed in: it cannot be turned back into an address and differs every day, so it cannot be followed across days. */
interface VisitorKeys
{
    /** @param string $day the Jakarta calendar day, `Y-m-d` */
    public function forVisitor(string $address, string $userAgent, string $day): string;
}
