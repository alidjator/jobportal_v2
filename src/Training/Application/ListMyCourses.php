<?php

namespace Src\Training\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Training\Domain\EnrolledCourse;

/**
 * The courses a job seeker is enrolled in on the training platform, found by the email of their account, which must be verified (anyone could type another person's
 * email). The platform sends no image with an enrolled course (its image links need the token), so a missing image is taken from the same course in the catalog.
 */
final readonly class ListMyCourses
{
    public function __construct(private ApplicantContacts $contacts, private TrainingCatalog $catalog) {}

    /**
     * @return list<EnrolledCourse>
     *
     * @throws AccessDenied `job-seeker-only`, `email-not-verified`
     * @throws ExternalServiceUnavailable
     */
    public function execute(Actor $actor): array
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker has courses.', 'job-seeker-only');
        }

        $contact = $this->contacts->contact($actor->id);

        if ($contact === null || ! $contact->emailVerified) {
            throw new AccessDenied('Verify your email to see your courses.', 'email-not-verified');
        }

        $enrolled = $this->catalog->enrollmentsOf($contact->email);

        $missing = array_filter($enrolled, fn (EnrolledCourse $course): bool => $course->course->imageUrl === null);

        return $missing === [] ? $enrolled : $this->withCatalogImages($enrolled);
    }

    /**
     * @param  list<EnrolledCourse>  $enrolled
     * @return list<EnrolledCourse>
     */
    private function withCatalogImages(array $enrolled): array
    {
        try {
            $images = [];

            foreach ($this->catalog->courses() as $course) {
                if ($course->imageUrl !== null) {
                    $images[$course->id] = $course->imageUrl;
                }
            }
        } catch (ExternalServiceUnavailable) {
            return $enrolled;
        }

        return array_map(
            fn (EnrolledCourse $course): EnrolledCourse => $course->course->imageUrl === null && isset($images[$course->course->id])
                ? new EnrolledCourse($course->course->withImage($images[$course->course->id]), $course->progress, $course->lastAccessAt)
                : $course,
            $enrolled,
        );
    }
}
