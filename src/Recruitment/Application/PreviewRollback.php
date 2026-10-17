<?php

namespace Src\Recruitment\Application;

use RuntimeException;

/** Thrown to undo a preview's writes: the use case ran the real code in a transaction to check and fill in the email, and the transaction must not commit. Carries the result. */
final class PreviewRollback extends RuntimeException
{
    /** @param  array{0: string, 1: array<string, string>}  $draft  the template key and its variables */
    public function __construct(public readonly array $draft)
    {
        parent::__construct('The preview is rolled back.');
    }
}
