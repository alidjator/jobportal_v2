<?php

namespace Src\Shared\Application\Applicant;

/**
 * Asks for a CV to be generated from the job seeker's profile instead of attaching an uploaded resume: the template (null for the default), the language of the
 * section titles (`id` or `en`; null for the account language) and which optional personal details to print (`photo`, `birth_date`, `gender`, `religion`,
 * `marital_status`, `nationality`). JobSeeker checks the values.
 */
final readonly class GeneratedCvSpec
{
    /** @param  list<string>  $include */
    public function __construct(public ?string $template = null, public ?string $language = null, public array $include = []) {}
}
