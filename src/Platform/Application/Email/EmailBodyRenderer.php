<?php

namespace Src\Platform\Application\Email;

/** Turns the Markdown body of an email into its HTML (inside the standard email layout) and plain-text forms. */
interface EmailBodyRenderer
{
    /** Raw HTML in the Markdown is shown as text and unsafe links are refused, so a body can never inject markup or scripts. */
    public function html(string $markdown): string;

    public function text(string $markdown): string;
}
