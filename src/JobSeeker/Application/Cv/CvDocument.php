<?php

namespace Src\JobSeeker\Application\Cv;

use Src\JobSeeker\Domain\CvTemplate;

/**
 * Everything a CV template prints, already worded in the language of the CV: the templates only lay it out. Sections with nothing in them are empty lists.
 * `photo` is a `data:` address of the picture, or null.
 */
final readonly class CvDocument
{
    /**
     * @param  array<string, string>  $labels
     * @param  list<array{label: string, value: string}>  $personal
     * @param  list<array{title: string, company: string, location: string|null, period: string, description: string|null}>  $experiences
     * @param  list<array{title: string, institution: string, period: string, gpa: string|null, description: string|null}>  $educations
     * @param  list<string>  $skills
     * @param  list<array{name: string, level: string}>  $languages
     * @param  list<array{label: string, url: string}>  $links
     */
    public function __construct(
        public CvTemplate $template,
        public string $language,
        public array $labels,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $location,
        public ?string $headline,
        public ?string $summary,
        public ?string $photo,
        public array $personal,
        public array $experiences,
        public array $educations,
        public array $skills,
        public array $languages,
        public array $links,
    ) {}
}
