<?php

namespace Src\Shared\Application\Email;

/**
 * Renders an email whose subject and Markdown body are written by a person at the time (a newsletter issue), not taken from a registered template. The same
 * safety rules apply as for templates: raw HTML in the body is shown as text and unsafe links are refused. Provided by Platform.
 */
interface AdHocEmails
{
    public function render(string $subject, string $markdown): RenderedEmail;
}
