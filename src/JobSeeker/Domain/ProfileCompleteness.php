<?php

namespace Src\JobSeeker\Domain;

/**
 * Whether a profile is complete enough to apply: a phone number, a province and a city, a resume, and at least one work experience or one education entry.
 * The apply step enforces it; clients use `missing` to guide the user.
 */
final readonly class ProfileCompleteness
{
    /** @param  list<string>  $missing  codes among `phone`, `location`, `history` and `resume` */
    private function __construct(public array $missing) {}

    public static function evaluate(JobSeekerProfile $profile, int $experiences, int $educations, int $resumes): self
    {
        $missing = [];

        if ($profile->phone === null) {
            $missing[] = 'phone';
        }

        if ($profile->provinceId === null || $profile->cityId === null) {
            $missing[] = 'location';
        }

        if ($experiences + $educations === 0) {
            $missing[] = 'history';
        }

        if ($resumes === 0) {
            $missing[] = 'resume';
        }

        return new self($missing);
    }

    public function isComplete(): bool
    {
        return $this->missing === [];
    }
}
