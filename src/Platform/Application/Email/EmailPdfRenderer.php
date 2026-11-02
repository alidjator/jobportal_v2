<?php

namespace Src\Platform\Application\Email;

/** Turns a rendered email (its subject and its HTML) into the bytes of a PDF, for the admin's preview. Implemented in Infrastructure. */
interface EmailPdfRenderer
{
    public function render(string $subject, string $html): string;
}
