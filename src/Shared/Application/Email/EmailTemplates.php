<?php

namespace Src\Shared\Application\Email;

/**
 * Renders an email template in a language: the admin's override for that language when there is one, else the default from the
 * catalog. Contexts call this when they build an email; asking for an unregistered key is a programming error and throws.
 */
interface EmailTemplates
{
    /** @param  array<string, string>  $variables values for the template's variables; unknown names are ignored */
    public function render(string $key, string $locale, array $variables): RenderedEmail;
}
