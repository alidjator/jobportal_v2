<?php

namespace Src\Training\Application;

use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Training\Domain\Course;
use Src\Training\Domain\EnrolledCourse;

/** The training platform (Moodle), read only. */
interface TrainingCatalog
{
    public const string UNAVAILABLE = 'training-unavailable';

    /**
     * Every course of the platform, without its front-page "site" course.
     *
     * @return list<Course>
     *
     * @throws ExternalServiceUnavailable `training-unavailable`
     */
    public function courses(): array;

    /**
     * The courses of the platform account that has this email; empty when there is no such account.
     *
     * @return list<EnrolledCourse>
     *
     * @throws ExternalServiceUnavailable `training-unavailable`
     */
    public function enrollmentsOf(string $email): array;
}
