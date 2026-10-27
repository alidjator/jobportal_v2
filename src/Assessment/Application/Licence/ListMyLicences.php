<?php

namespace Src\Assessment\Application\Licence;

use Src\Assessment\Application\Review\ReviewAccess;
use Src\Assessment\Domain\LicenceSubmission;
use Src\Assessment\Domain\LicenceSubmissionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The psychologist's own submissions, newest first, with the decision and the note of the admin. */
final readonly class ListMyLicences
{
    public function __construct(
        private ReviewAccess $access,
        private LicenceSubmissionRepository $submissions,
    ) {}

    /**
     * @return list<LicenceSubmission>
     *
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     */
    public function execute(Actor $actor): array
    {
        return $this->submissions->ofPsychologist((int) $this->access->psychologist($actor)->id);
    }
}
