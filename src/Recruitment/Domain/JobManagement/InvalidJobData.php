<?php

namespace Src\Recruitment\Domain\JobManagement;

use Src\Shared\Domain\Exceptions\DomainException;

/** Data lowongan melanggar aturan domain (mis. gaji min > max). `field` menunjuk input terkait. */
final class InvalidJobData extends DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
