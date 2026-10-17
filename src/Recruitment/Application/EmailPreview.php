<?php

namespace Src\Recruitment\Application;

/** An email as the applicant would get it: the template in force for their language, with the real values. `calendarAttached` says an `.ics` file would go with it; `emailEnabled` is false when the applicant turned the email off, so only the in-app notice reaches them. */
final readonly class EmailPreview
{
    public function __construct(
        public string $template,
        public string $subject,
        public string $html,
        public string $text,
        public string $locale,
        public bool $emailEnabled,
        public bool $calendarAttached,
    ) {}
}
