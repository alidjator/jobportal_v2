<?php

namespace Src\Shared\Application\Email;

/**
 * Shows an email as the recipient would get it, without sending or saving anything: the template in force (an admin's override included) with these variables, inside
 * the standard layout, with the company's logo and the fixed line when the variables name a company (`company_id`). Provided by Platform.
 */
interface EmailPreviewer
{
    /** @param  array<string, string>  $variables  the template variables, with `name` and, for a company's email, `company_id` and `company_name` */
    public function render(string $templateKey, string $locale, array $variables): RenderedEmail;
}
