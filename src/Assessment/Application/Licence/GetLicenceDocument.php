<?php

namespace Src\Assessment\Application\Licence;

use Src\Assessment\Application\Admin\AdminOnly;
use Src\Assessment\Application\Delivery\AttachmentFile;
use Src\Assessment\Domain\LicenceSubmissionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The document of a submission, for the platform admin who decides it. */
final readonly class GetLicenceDocument
{
    public function __construct(
        private LicenceSubmissionRepository $submissions,
        private FileStorage $files,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $submissionId): AttachmentFile
    {
        AdminOnly::assert($actor, 'read a licence document');

        $submission = $this->submissions->find($submissionId) ?? throw EntityNotFound::of('LicenceSubmission', $submissionId);
        $contents = $this->files->get($submission->documentPath) ?? throw EntityNotFound::of('LicenceSubmission', $submissionId);

        return new AttachmentFile($contents, 'application/pdf', $submission->documentName);
    }
}
