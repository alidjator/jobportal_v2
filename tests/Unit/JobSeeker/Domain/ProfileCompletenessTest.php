<?php

namespace Tests\Unit\JobSeeker\Domain;

use PHPUnit\Framework\TestCase;
use Src\JobSeeker\Domain\JobSeekerProfile;
use Src\JobSeeker\Domain\ProfileCompleteness;

final class ProfileCompletenessTest extends TestCase
{
    private function filled(): JobSeekerProfile
    {
        return JobSeekerProfile::empty(1)->with(['phone' => '+6281234567890', 'provinceId' => 1, 'cityId' => 2]);
    }

    public function test_an_empty_profile_misses_everything(): void
    {
        $completeness = ProfileCompleteness::evaluate(JobSeekerProfile::empty(1), 0, 0, 0);

        $this->assertFalse($completeness->isComplete());
        $this->assertSame(['phone', 'location', 'history', 'resume'], $completeness->missing);
    }

    public function test_one_experience_or_one_education_is_enough_for_the_history(): void
    {
        $this->assertTrue(ProfileCompleteness::evaluate($this->filled(), 1, 0, 1)->isComplete());
        $this->assertTrue(ProfileCompleteness::evaluate($this->filled(), 0, 1, 1)->isComplete());
        $this->assertTrue(ProfileCompleteness::evaluate($this->filled(), 3, 2, 5)->isComplete());
        $this->assertSame(['history'], ProfileCompleteness::evaluate($this->filled(), 0, 0, 1)->missing);
    }

    public function test_a_resume_is_always_needed(): void
    {
        $this->assertSame(['resume'], ProfileCompleteness::evaluate($this->filled(), 1, 1, 0)->missing);
    }

    public function test_the_location_needs_a_province_and_a_city(): void
    {
        $province = JobSeekerProfile::empty(1)->with(['phone' => '+6281234567890', 'provinceId' => 1]);

        $this->assertSame(['location'], ProfileCompleteness::evaluate($province, 1, 0, 1)->missing);
    }
}
